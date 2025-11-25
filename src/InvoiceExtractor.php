<?php

namespace Dave\UblBuilder;

use Exception;

/**
 * Invoice Extractor
 * 
 * Extracts structured invoice data from HTML using OpenAI API
 */
class InvoiceExtractor
{
    private OpenApiClient $client;
    private string $model;
    private InvoiceValidator $validator;

    /**
     * Constructor
     *
     * @param OpenApiClient $client OpenAI client instance
     * @param string $model Model to use (default: gpt-4o-mini)
     */
    public function __construct(OpenApiClient $client, string $model = 'gpt-4o-mini')
    {
        $this->client = $client;
        $this->model = $model;
        $this->validator = new InvoiceValidator();
    }

    /**
     * Extract invoice data from HTML
     *
     * @param string $htmlContent HTML content of the invoice
     * @return array Structured invoice data
     * @throws Exception If extraction fails
     */
    public function extractFromHtml(string $htmlContent): array
    {
        $prompt = $this->buildExtractionPrompt();
        
        $messages = [
            [
                'role' => 'system',
                'content' => $prompt
            ],
            [
                'role' => 'user',
                'content' => "Extract invoice data from this HTML:\n\n" . $htmlContent
            ]
        ];

        $response = $this->client->chatCompletion($this->model, $messages, [
            'temperature' => 0.1,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ]);

        $content = $this->client->extractContent($response);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse OpenAI response: ' . json_last_error_msg());
        }

        $this->validateExtractedData($data);
        
        // Apply intelligent fallbacks for missing data
        $this->validator->applyIntelligentFallbacks($data);
        
        return $data;
    }

    /**
     * Build extraction prompt for OpenAI
     *
     * @return string Prompt text
     */
    private function buildExtractionPrompt(): string
    {
        return <<<PROMPT
You are an expert at extracting structured invoice data from HTML documents.
Extract all relevant invoice information and return it as a JSON object with the following structure:

{
  "invoice": {
    "number": "Invoice number (e.g., F-202000012, DMO250070)",
    "issueDate": "Issue date in YYYY-MM-DD format",
    "dueDate": "Due date in YYYY-MM-DD format (if available)",
    "typeCode": "Invoice type code (380=Commercial Invoice, 381=Credit Note)",
    "buyerReference": "Buyer reference or purchase order number",
    "currency": "Currency code (default to EUR if not specified)",
    "notes": "Any notes or comments on the invoice"
  },
  "seller": {
    "name": "Legal business name",
    "tradingName": "Trading name (if different from legal name)",
    "vatNumber": "VAT number (e.g., ESA00000000, B0123456)",
    "companyId": "Company/KVK number",
    "companyIdScheme": "Scheme for company ID (0106 for NL KVK in PEPPOL)",
    "electronicAddress": "Electronic address for e-invoicing (typically 0106:KVKNUMBER for NL)",
    "electronicAddressScheme": "Scheme code (0106 for NL KVK PEPPOL, 0088 for GLN)",
    "address": "Full street address",
    "city": "City name",
    "postalCode": "Postal/ZIP code",
    "country": "Two-letter country code (e.g., NL, DE, FR)",
    "contactName": "Contact person name",
    "contactPhone": "Contact phone number",
    "contactEmail": "Contact email address"
  },
  "buyer": {
    "name": "Buyer company name",
    "vatNumber": "Buyer VAT number (if available)",
    "companyId": "Buyer company ID (if available)",
    "electronicAddress": "Buyer electronic address",
    "electronicAddressScheme": "Scheme code for buyer's electronic address",
    "address": "Buyer street address",
    "city": "Buyer city",
    "postalCode": "Buyer postal code",
    "country": "Buyer country code",
    "contactName": "Buyer contact name",
    "reference": "Buyer reference or cost center"
  },
  "lines": [
    {
      "id": 1,
      "name": "Product or service name",
      "description": "Additional description (if any)",
      "quantity": 10.5,
      "unitCode": "Unit code (H87=unit/piece, C62=unit, HUR=hour)",
      "price": 100.00,
      "priceQuantity": 1,
      "vatRate": 21,
      "vatCategory": "VAT category code (S=Standard, Z=Zero rated, E=Exempt)",
      "netAmount": "Net amount for line (quantity * price)"
    }
  ],
  "totals": {
    "lineExtension": "Sum of line amounts excl. VAT",
    "taxExclusive": "Total amount excl. VAT (same as lineExtension)",
    "taxAmount": "Total VAT amount",
    "taxInclusive": "Total amount incl. VAT",
    "payableAmount": "Amount to be paid (usually same as taxInclusive)"
  },
  "taxBreakdown": [
    {
      "rate": 21,
      "category": "S",
      "taxableAmount": 100.00,
      "taxAmount": 21.00
    }
  ],
  "payment": {
    "paymentMeansCode": "Payment means code (31=SEPA Credit Transfer, 30=Credit Transfer)",
    "iban": "Bank account IBAN",
    "bic": "Bank BIC/SWIFT code (optional for NL->NL)",
    "paymentTerms": "Payment terms description (e.g., '30 days net')"
  }
}

IMPORTANT INSTRUCTIONS:
1. Extract ALL numerical values as numbers, not strings
2. Parse dates in YYYY-MM-DD format (convert from DD-MM-YYYY if needed)
3. For VAT rates, use INTEGER format (e.g., 21 for 21%, 9 for 9%, 0 for 0%)
4. For typeCode, use 380 for standard invoices, 381 for credit notes
5. Each line must have a unique 'id' field (1, 2, 3, etc.)
6. If a field is not found in the HTML, omit it or set it to null
5. Calculate netAmount for each line if not explicitly stated: quantity * price
7. For Dutch invoices, common VAT rates are 21 (high), 9 (low), and 0
8. Extract company IDs (KVK numbers) from the HTML
9. For NL companies, use companyIdScheme 0106 and electronicAddressScheme 0106
10. VAT category codes: S=Standard rate, Z=Zero rated, E=Exempt, AE=Reverse charge
11. Ensure all amounts are properly calculated and match the invoice totals
12. paymentMeansCode should be 31 for SEPA transfers (most common in NL)
13. unitCode should be H87 for 'unit/piece', HUR for 'hour', C62 for generic 'unit'

Return ONLY the JSON object, no additional text or explanations.
PROMPT;
    }

    /**
     * Validate and normalize extracted data with Dutch defaults
     *
     * @param array $data Extracted data
     * @throws Exception If validation fails
     */
    private function validateExtractedData(array &$data): void
    {
        // Check required top-level keys
        if (!isset($data['invoice'])) {
            throw new Exception('Missing required field: invoice');
        }
        if (!isset($data['seller'])) {
            throw new Exception('Missing required field: seller');
        }
        if (!isset($data['buyer'])) {
            throw new Exception('Missing required field: buyer');
        }
        if (!isset($data['lines']) || !is_array($data['lines'])) {
            throw new Exception('Missing or invalid field: lines');
        }

        // Apply defaults to invoice
        if (empty($data['invoice']['number'])) {
            throw new Exception('Missing required field: invoice.number');
        }
        if (empty($data['invoice']['issueDate'])) {
            throw new Exception('Missing required field: invoice.issueDate');
        }
        if (empty($data['invoice']['currency'])) {
            $data['invoice']['currency'] = 'EUR';
        }
        if (empty($data['invoice']['typeCode'])) {
            $data['invoice']['typeCode'] = '380'; // Default to commercial invoice
        }

        // Apply defaults to seller (Dutch context)
        if (empty($data['seller']['name'])) {
            throw new Exception('Missing required field: seller.name');
        }
        if (empty($data['seller']['country'])) {
            $data['seller']['country'] = 'NL'; // Default to Netherlands
        }
        if (empty($data['seller']['companyIdScheme']) && !empty($data['seller']['companyId'])) {
            $data['seller']['companyIdScheme'] = '0106'; // NL KVK for PEPPOL
        }
        if (empty($data['seller']['electronicAddressScheme'])) {
            $data['seller']['electronicAddressScheme'] = '0106'; // NL KVK for PEPPOL
        }

        // Apply defaults to buyer (Dutch context)
        if (empty($data['buyer']['name'])) {
            throw new Exception('Missing required field: buyer.name');
        }
        if (empty($data['buyer']['country'])) {
            $data['buyer']['country'] = 'NL'; // Default to Netherlands
        }
        if (empty($data['buyer']['companyIdScheme']) && !empty($data['buyer']['companyId'])) {
            $data['buyer']['companyIdScheme'] = '0106'; // NL KVK for PEPPOL
        }
        if (empty($data['buyer']['electronicAddressScheme'])) {
            $data['buyer']['electronicAddressScheme'] = '0106'; // NL KVK for PEPPOL
        }

        // Check lines
        if (empty($data['lines'])) {
            throw new Exception('At least one invoice line is required');
        }

        foreach ($data['lines'] as $index => &$line) {
            // Add line ID if missing
            if (!isset($line['id'])) {
                $line['id'] = $index + 1;
            }
            
            if (empty($line['name'])) {
                throw new Exception("Missing required field: lines[$index].name");
            }
            if (!isset($line['quantity']) || !is_numeric($line['quantity'])) {
                throw new Exception("Missing or invalid field: lines[$index].quantity");
            }
            if (!isset($line['price']) || !is_numeric($line['price'])) {
                throw new Exception("Missing or invalid field: lines[$index].price");
            }
            
            // Apply defaults for VAT
            if (!isset($line['vatRate'])) {
                $line['vatRate'] = 21.0; // Default Dutch high VAT rate
            }
            if (empty($line['vatCategory'])) {
                // Determine category based on rate
                if ($line['vatRate'] == 0) {
                    $line['vatCategory'] = 'Z'; // Zero rated
                } elseif ($line['vatRate'] == 9) {
                    $line['vatCategory'] = 'S'; // Standard (low rate)
                } else {
                    $line['vatCategory'] = 'S'; // Standard rate
                }
            }
            if (empty($line['unitCode'])) {
                $line['unitCode'] = 'H87'; // Default unit code (unit/piece)
            }
        }
    }
}
