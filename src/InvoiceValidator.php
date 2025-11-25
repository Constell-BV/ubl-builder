<?php

namespace Dave\UblBuilder;

/**
 * Invoice Validator
 * 
 * Validates invoice data and applies intelligent fallbacks
 * using legally compliant dummy data for missing required fields
 */
class InvoiceValidator
{
    /**
     * Apply intelligent fallbacks for missing required fields
     * Uses legally compliant dummy data that passes UBL/Peppol validation
     *
     * @param array $data Invoice data (modified in place)
     * @return void
     */
    public function applyIntelligentFallbacks(array &$data): void
    {
        // Initialize metadata if not present
        if (!isset($data['_metadata'])) {
            $data['_metadata'] = [
                'dummyFields' => [],
                'missingFields' => [],
                'warnings' => [],
                'extractionTimestamp' => date('c')
            ];
        }

        // Apply all fallback rules
        $this->applyBuyerAddressFallbacks($data);
        $this->applyElectronicAddressFallbacks($data);
        $this->applyCompanyIdSchemeFallbacks($data);
        $this->applyBuyerReferenceFallback($data);
        $this->applyPaymentInfoFallbacks($data);
        $this->validateTotals($data);
    }

    /**
     * Apply buyer address fallbacks
     *
     * @param array $data Invoice data
     * @return void
     */
    private function applyBuyerAddressFallbacks(array &$data): void
    {
        if (empty($data['buyer']['address'])) {
            $data['_metadata']['missingFields'][] = 'buyer.address';
            
            // Use reserved test address (RFC compliant)
            $data['buyer']['address'] = ['Teststraat 1'];
            $data['buyer']['city'] = $data['buyer']['city'] ?? 'Amsterdam';
            $data['buyer']['postalCode'] = $data['buyer']['postalCode'] ?? '1000AA'; // Valid format, non-existent
            
            $data['_metadata']['dummyFields'][] = 'buyer.address';
            $data['_metadata']['warnings'][] = 'Buyer address missing - using legally compliant placeholder';
        }
    }

    /**
     * Apply electronic address fallbacks and fix schemes
     *
     * @param array $data Invoice data
     * @return void
     */
    private function applyElectronicAddressFallbacks(array &$data): void
    {
        // Buyer electronic address
        if (empty($data['buyer']['electronicAddress'])) {
            $data['_metadata']['missingFields'][] = 'buyer.electronicAddress';
            
            // RFC 6761 reserved .invalid TLD for non-routable addresses
            $data['buyer']['electronicAddress'] = 'noreply@buyer.invalid';
            $data['buyer']['electronicAddressScheme'] = '9957'; // Email scheme (ISO 6523)
            
            $data['_metadata']['dummyFields'][] = 'buyer.electronicAddress';
            $data['_metadata']['warnings'][] = 'Buyer electronic address missing - using RFC 6761 compliant placeholder';
        } else {
            // Fix scheme based on format
            $this->fixElectronicAddressScheme($data['buyer']);
        }

        // Seller electronic address scheme fix
        if (!empty($data['seller']['electronicAddress'])) {
            $this->fixElectronicAddressScheme($data['seller']);
        }
    }

    /**
     * Fix electronic address scheme based on address format
     *
     * @param array $party Party data (buyer or seller)
     * @return void
     */
    private function fixElectronicAddressScheme(array &$party): void
    {
        if (filter_var($party['electronicAddress'], FILTER_VALIDATE_EMAIL)) {
            $party['electronicAddressScheme'] = '9957'; // Email scheme
        } elseif (empty($party['electronicAddressScheme'])) {
            $party['electronicAddressScheme'] = '0088'; // Default to GLN
        }
    }

    /**
     * Apply company ID scheme fallbacks
     *
     * @param array $data Invoice data
     * @return void
     */
    private function applyCompanyIdSchemeFallbacks(array &$data): void
    {
        // Seller company ID scheme
        if (!empty($data['seller']['companyId']) && empty($data['seller']['companyIdScheme'])) {
            $data['seller']['companyIdScheme'] = '0106'; // Dutch KVK scheme for PEPPOL (ISO 6523)
            $data['_metadata']['warnings'][] = 'Seller company ID scheme auto-set to 0106 (NL KVK PEPPOL)';
        }

        // Buyer company ID scheme
        if (!empty($data['buyer']['companyId']) && empty($data['buyer']['companyIdScheme'])) {
            $data['buyer']['companyIdScheme'] = '0106'; // Dutch KVK scheme for PEPPOL
            $data['_metadata']['warnings'][] = 'Buyer company ID scheme auto-set to 0106 (NL KVK PEPPOL)';
        }
    }

    /**
     * Apply buyer reference fallback
     *
     * @param array $data Invoice data
     * @return void
     */
    private function applyBuyerReferenceFallback(array &$data): void
    {
        // Buyer reference is required by Peppol
        if (empty($data['invoice']['buyerReference'])) {
            $data['_metadata']['missingFields'][] = 'invoice.buyerReference';
            
            // Use invoice number as fallback (common practice)
            $data['invoice']['buyerReference'] = $data['invoice']['number'];
            
            $data['_metadata']['dummyFields'][] = 'invoice.buyerReference';
            $data['_metadata']['warnings'][] = 'Buyer reference missing - using invoice number as fallback';
        }
    }

    /**
     * Apply payment info fallbacks for paid/credit invoices
     *
     * @param array $data Invoice data
     * @return void
     */
    private function applyPaymentInfoFallbacks(array &$data): void
    {
        // Check if invoice is paid or credit note (no due date)
        $isPaidOrCredit = empty($data['invoice']['dueDate']);
        
        if ($isPaidOrCredit && empty($data['payment']['iban'])) {
            $data['_metadata']['missingFields'][] = 'payment.iban';
            
            // Valid IBAN format with invalid check digit (00)
            // This ensures format compliance but won't route to a real account
            $data['payment']['iban'] = 'NL00INGB0000000000';
            $data['payment']['bic'] = 'INGBNL2A'; // Valid BIC format (ISO 9362)
            $data['payment']['paymentMeansCode'] = '31'; // SEPA Credit Transfer
            
            $data['_metadata']['dummyFields'][] = 'payment';
            $data['_metadata']['warnings'][] = 'Payment info missing (paid/credit invoice) - using compliant placeholder';
        }
        
        // Ensure paymentMeansCode is set
        if (!empty($data['payment']['iban']) && empty($data['payment']['paymentMeansCode'])) {
            $data['payment']['paymentMeansCode'] = '31'; // Default to SEPA Credit Transfer
            $data['_metadata']['warnings'][] = 'Payment means code auto-set to 31 (SEPA Credit Transfer)';
        }
    }

    /**
     * Validate that totals match line calculations
     *
     * @param array $data Invoice data
     * @return void
     */
    private function validateTotals(array &$data): void
    {
        if (empty($data['lines'])) {
            return;
        }

        // Calculate totals from lines
        $calculatedNet = 0;
        $calculatedVat = 0;
        
        foreach ($data['lines'] as $line) {
            $lineNet = $line['quantity'] * $line['price'];
            $calculatedNet += $lineNet;
            
            if (isset($line['vatRate'])) {
                $calculatedVat += $lineNet * ($line['vatRate'] / 100);
            }
        }
        
        // Ensure all required total fields exist
        if (!isset($data['totals']['lineExtension'])) {
            $data['totals']['lineExtension'] = $calculatedNet;
        }
        if (!isset($data['totals']['taxExclusive'])) {
            $data['totals']['taxExclusive'] = $calculatedNet;
        }
        if (!isset($data['totals']['taxAmount'])) {
            $data['totals']['taxAmount'] = $calculatedVat;
        }
        if (!isset($data['totals']['taxInclusive'])) {
            $data['totals']['taxInclusive'] = $calculatedNet + $calculatedVat;
        }
        if (!isset($data['totals']['payableAmount'])) {
            $data['totals']['payableAmount'] = $calculatedNet + $calculatedVat;
        }
        
        // Validate against stated totals
        $difference = abs($calculatedNet - $data['totals']['lineExtension']);
        
        // Allow 2 cent rounding difference
        if ($difference > 0.02) {
            $data['_metadata']['warnings'][] = sprintf(
                'Total mismatch: calculated %.2f vs stated %.2f (diff: %.2f)',
                $calculatedNet,
                $data['totals']['lineExtension'],
                $difference
            );
        }
    }

    /**
     * Check if buyer appears to be a business (B2B) vs consumer (B2C)
     *
     * @param array $buyer Buyer data
     * @return bool True if business, false if consumer
     */
    public function isBusinessToBusiness(array $buyer): bool
    {
        // Check for business indicators
        if (!empty($buyer['companyId']) || !empty($buyer['vatNumber'])) {
            return true;
        }

        // Check if name looks like a company
        return $this->looksLikeCompanyName($buyer['name'] ?? '');
    }

    /**
     * Check if name looks like a company name
     *
     * @param string $name Party name
     * @return bool True if looks like company
     */
    private function looksLikeCompanyName(string $name): bool
    {
        $companyIndicators = [
            'BV', 'B.V.', 'NV', 'N.V.', 'VOF', 'V.O.F.',
            'GmbH', 'Ltd', 'LLC', 'Inc', 'Corp',
            'SA', 'SARL', 'SRL', 'AG'
        ];

        foreach ($companyIndicators as $indicator) {
            if (stripos($name, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }
}
