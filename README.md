# UBL Invoice Builder

A PHP-based tool for extracting invoice data from HTML files and generating UBL (Universal Business Language) XML invoices using OpenAI's GPT models. The system includes intelligent data validation, quality analysis, and legally compliant fallbacks for missing information.

## Features

- 🤖 **AI-Powered Extraction**: Uses OpenAI GPT models to extract invoice data from HTML
- ✅ **100% UBL Validation Success**: Smart fallbacks ensure all invoices pass UBL validation
- 📊 **Data Quality Analysis**: Comprehensive scoring and reporting system
- 🔍 **Detailed Reporting**: JSON and console reports with quality metrics
- ⚖️ **Legally Compliant**: All dummy data follows RFC and ISO standards
- 🎯 **B2B/B2C Detection**: Automatically adjusts validation rules based on invoice type
- 🛡️ **Separation of Concerns**: Clean architecture with specialized classes

## Quick Start

```bash
# 1. Install dependencies
composer install

# 2. Configure API key
cp .env.example .env
# Edit .env and add your OpenAI API key

# 3. Place HTML invoices in examples/ directory

# 4. Run the generator
php generate.php

# 5. Check output/ directory for XML, JSON, and reports
```

## Complete Example: Extract → Generate

Here's a complete workflow showing how to extract data from HTML and generate a UBL XML invoice:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dave\UblBuilder\OpenApiClient;
use Dave\UblBuilder\InvoiceExtractor;
use Dave\UblBuilder\InvoiceValidator;
use Dave\UblBuilder\InvoiceBuilder;
use Einvoicing\Writers\UblWriter;

// Step 1: EXTRACT - Read HTML invoice
$html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <h1>Invoice #INV-2024-001</h1>
    <p>Date: 2024-11-24</p>
    <p>Due Date: 2024-12-24</p>
    
    <h2>From:</h2>
    <p>
        ACME Corporation<br>
        VAT: NL123456789B01<br>
        KVK: 12345678<br>
        123 Main Street<br>
        1012AB Amsterdam, NL
    </p>
    
    <h2>To:</h2>
    <p>
        John Doe<br>
        john@example.com
    </p>
    
    <h2>Items:</h2>
    <table>
        <tr>
            <th>Description</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>VAT</th>
            <th>Total</th>
        </tr>
        <tr>
            <td>Widget Pro</td>
            <td>2</td>
            <td>€50.00</td>
            <td>21%</td>
            <td>€100.00</td>
        </tr>
    </table>
    
    <p>Subtotal: €100.00</p>
    <p>VAT (21%): €21.00</p>
    <p><strong>Total: €121.00</strong></p>
</body>
</html>
HTML;

// Step 2: EXTRACT - Use AI to extract invoice data
$client = new OpenApiClient($_ENV['OPENAI_API_KEY']);
$extractor = new InvoiceExtractor($client, $_ENV['OPENAI_MODEL']);
$invoiceData = $extractor->extractFromHtml($html);

echo "✅ Invoice data extracted successfully!\n";
echo "   Invoice Number: {$invoiceData['invoice']['number']}\n";
echo "   Seller: {$invoiceData['seller']['name']}\n";
echo "   Total: €{$invoiceData['totals']['taxInclusive']}\n\n";

// Step 3: VALIDATE - Apply smart fallbacks for missing data
$validator = new InvoiceValidator();
$validatedData = $validator->validate($invoiceData);

echo "✅ Invoice data validated with smart fallbacks applied\n\n";

// Step 4: GENERATE - Build UBL invoice object
$builder = new InvoiceBuilder();
$invoice = $builder->build($validatedData);

// Step 5: GENERATE - Validate UBL compliance
$invoice->validate();
echo "✅ UBL invoice validated successfully!\n\n";

// Step 6: GENERATE - Export to UBL XML
$writer = new UblWriter();
$xml = $writer->export($invoice);

// Save the result
file_put_contents('output/invoice.xml', $xml);
file_put_contents('output/invoice.json', json_encode($validatedData, JSON_PRETTY_PRINT));

echo "✅ Files generated:\n";
echo "   - output/invoice.xml (UBL XML invoice)\n";
echo "   - output/invoice.json (Extracted data)\n";
```

**Output:**
```
✅ Invoice data extracted successfully!
   Invoice Number: INV-2024-001
   Seller: ACME Corporation
   Total: €121.00

✅ Invoice data validated with smart fallbacks applied

✅ UBL invoice validated successfully!

✅ Files generated:
   - output/invoice.xml (UBL XML invoice)
   - output/invoice.json (Extracted data)
```

## Validating UBL Invoices

Once you've generated a UBL XML invoice, you can validate it to ensure compliance:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Einvoicing\Readers\UblReader;
use Einvoicing\Presets\Peppol;

try {
    // Read the generated UBL XML file
    $reader = new UblReader();
    $invoice = $reader->import(file_get_contents('output/invoice.xml'));
    
    // Set validation preset (e.g., Peppol BIS 3.0)
    $invoice->setBusinessProcess(new Peppol());
    
    // Validate the invoice
    $validationErrors = $invoice->validate();
    
    if (empty($validationErrors)) {
        echo "✅ Invoice is valid and UBL compliant!\n\n";
        
        // Display invoice details
        echo "Invoice Details:\n";
        echo "  Number: " . $invoice->getNumber() . "\n";
        echo "  Issue Date: " . $invoice->getIssueDate()->format('Y-m-d') . "\n";
        echo "  Seller: " . $invoice->getSeller()->getName() . "\n";
        echo "  Buyer: " . $invoice->getBuyer()->getName() . "\n";
        echo "  Total Amount: " . $invoice->getTotals()->payableAmount . " ";
        echo $invoice->getCurrency() . "\n";
    } else {
        echo "❌ Validation errors found:\n";
        foreach ($validationErrors as $error) {
            echo "  - " . $error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error reading or validating invoice: " . $e->getMessage() . "\n";
}
```

**Output for valid invoice:**
```
✅ Invoice is valid and UBL compliant!

Invoice Details:
  Number: INV-2024-001
  Issue Date: 2024-11-24
  Seller: ACME Corporation
  Buyer: John Doe
  Total Amount: 121.00 EUR
```

**Output for invalid invoice:**
```
❌ Validation errors found:
  - Buyer party name is required
  - Invoice line item description is required
  - Tax amount does not match calculated tax
```

### Validation Presets

Different validation rules can be applied depending on your requirements:

```php
use Einvoicing\Presets\Peppol;
use Einvoicing\Presets\En16931;

// Peppol BIS 3.0 (strictest - for cross-border invoicing)
$invoice->setBusinessProcess(new Peppol());

// EN 16931 (European standard)
$invoice->setBusinessProcess(new En16931());

// No preset (basic UBL validation only)
// Don't call setBusinessProcess()
```

## Table of Contents

- [Extract: AI-Powered Data Extraction](#extract-ai-powered-data-extraction)
- [Generate: UBL Invoice Creation](#generate-ubl-invoice-creation)
- [Analyze: Quality Reporting](#analyze-quality-reporting)
- [Installation & Setup](#installation--setup)
- [Reference](#reference)

---

# Extract: AI-Powered Data Extraction

The extraction system uses OpenAI's GPT models to intelligently extract structured invoice data from HTML documents. The extractor is designed to handle various invoice formats and layouts automatically.

## How Extraction Works

1. **HTML Input**: Reads invoice HTML files from the `examples/` directory
2. **AI Processing**: Sends HTML to OpenAI API with specialized extraction prompt
3. **Structured Output**: Receives JSON-formatted invoice data
4. **Validation**: Validates extracted data and applies Dutch defaults
5. **Smart Fallbacks**: Fills in missing required fields with legally compliant values

## Extraction Capabilities

The AI extractor can identify and extract:

**Invoice Information:**
- Invoice number
- Issue date and due date
- Currency
- Notes and comments

**Party Information (Seller & Buyer):**
- Legal and trading names
- VAT numbers
- Company IDs (KVK numbers)
- Electronic addresses (GLN, SIRENE, etc.)
- Full addresses (street, city, postal code, country)
- Contact details (name, phone, email)

**Line Items:**
- Product/service names and descriptions
- Quantities and units
- Prices and net amounts
- VAT rates and categories

**Financial Totals:**
- Net amounts (excluding VAT)
- VAT amounts with breakdown by rate
- Gross totals (including VAT)

**Payment Information:**
- IBAN and BIC codes
- Account holder names
- Payment references and terms

## Model Selection

The extractor supports different OpenAI models, configured in `.env`:

```env
# Recommended for production - better accuracy
OPENAI_MODEL=gpt-4o

# Recommended for testing - faster and cheaper
OPENAI_MODEL=gpt-4o-mini
```

**Model Comparison:**

| Model | Accuracy | Speed | Cost | Best For |
|-------|----------|-------|------|----------|
| gpt-4o | Excellent | Moderate | Higher | Production, complex invoices |
| gpt-4o-mini | Very Good | Fast | Lower | Development, simple invoices |

## Dutch Invoice Defaults

The extractor applies intelligent defaults for Dutch invoices:

- **Currency**: Defaults to EUR if not specified
- **Country**: Defaults to NL (Netherlands) for seller and buyer
- **VAT Rates**: Recognizes Dutch rates (21% high, 9% low, 0%)
- **VAT Categories**: Automatically maps rates to categories (S/Z/E)
- **Electronic Address Scheme**: Defaults to 0088 (GLN)
- **Unit Codes**: Defaults to C62 (unit/piece)

## Customizing the Extractor

You can customize the extraction behavior by modifying `src/InvoiceExtractor.php`:

**1. Change the Extraction Prompt:**

Edit the `buildExtractionPrompt()` method to:
- Add language-specific instructions
- Include industry-specific fields
- Adjust validation rules

**2. Modify Default Values:**

Edit the `validateExtractedData()` method to:
- Change default country codes
- Adjust VAT rate defaults
- Set different electronic address schemes

**3. Add Custom Processing:**

Extend the `extractFromHtml()` method to:
- Pre-process HTML before extraction
- Post-process extracted data
- Add custom validation rules

## Example: Custom Extraction

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dave\UblBuilder\OpenApiClient;
use Dave\UblBuilder\InvoiceExtractor;

// Initialize OpenAI client
$client = new OpenApiClient($_ENV['OPENAI_API_KEY']);

// Create extractor with specific model
$extractor = new InvoiceExtractor($client, 'gpt-4o');

// Read HTML invoice
$html = file_get_contents('path/to/invoice.html');

// Extract data
$invoiceData = $extractor->extractFromHtml($html);

// Access extracted data
echo "Invoice Number: " . $invoiceData['invoice']['number'] . "\n";
echo "Seller: " . $invoiceData['seller']['name'] . "\n";
echo "Total Amount: " . $invoiceData['totals']['grossAmount'] . "\n";

// Save to JSON
file_put_contents('extracted-data.json', json_encode($invoiceData, JSON_PRETTY_PRINT));
```

## Extraction Tips for Best Results

**HTML Structure:**
- Use semantic HTML tags (`<table>`, `<address>`, `<h1>`, etc.)
- Label sections clearly (Invoice, Seller, Buyer, Items)
- Keep consistent formatting

**Data Clarity:**
- Format dates consistently (preferably YYYY-MM-DD or DD-MM-YYYY)
- Use clear labels (Invoice Number, VAT Number, Total, etc.)
- Separate amounts with clear currency symbols (€, EUR)

**Complete Information:**
- Include all party details (addresses, VAT numbers, contact info)
- Show VAT breakdown with rates
- List all line items in a table format

**Example of Well-Structured HTML:**

```html
<h1>Invoice #INV-2024-001</h1>
<p>Date: 2024-11-24</p>
<p>Due Date: 2024-12-24</p>

<h2>Seller Details</h2>
<p>
  Company Name: ACME Corp<br>
  VAT: NL123456789B01<br>
  Address: Main Street 123, 1012AB Amsterdam, NL
</p>

<h2>Invoice Items</h2>
<table>
  <tr>
    <th>Item</th>
    <th>Quantity</th>
    <th>Price</th>
    <th>VAT</th>
    <th>Total</th>
  </tr>
  <tr>
    <td>Product A</td>
    <td>2</td>
    <td>€50.00</td>
    <td>21%</td>
    <td>€100.00</td>
  </tr>
</table>

<h2>Totals</h2>
<p>Subtotal: €100.00</p>
<p>VAT (21%): €21.00</p>
<p>Total: €121.00</p>
```

## Troubleshooting Extraction Issues

**Low Quality Scores:**
- Switch from `gpt-4o-mini` to `gpt-4o` for better accuracy
- Improve HTML structure and labeling
- Add more context and clear section headers

**Missing Data:**
- Check if data is present in HTML
- Review extraction prompt for field coverage
- Verify field names match expected format

**Incorrect VAT Calculations:**
- Ensure VAT rates are clearly labeled in HTML
- Check if amounts include or exclude VAT
- Verify currency symbols are consistent

**Date Format Issues:**
- Use standard date formats (YYYY-MM-DD preferred)
- Include clear date labels (Issue Date, Due Date)
- Ensure dates are visible and not embedded in text

---

# Generate: UBL Invoice Creation

## Understanding the Pipeline

The generation process follows these steps:

1. **Extract** - Parse HTML and extract structured data (JSON)
2. **Validate** - Apply smart fallbacks for missing data
3. **Build** - Construct UBL invoice object
4. **Export** - Generate UBL XML file

## Running the Generator

### Basic Usage

Place your HTML invoice files in the `examples/` directory, then run:

```bash
php generate.php
```

The script will:
1. Read all HTML files from `examples/`
2. Extract invoice data using OpenAI
3. Validate and apply smart fallbacks
4. Generate UBL XML files in `output/`
5. Save extracted JSON data in `output/`
6. Create a comprehensive quality report in `output/extraction-report.json`

### Output Files

For each invoice `invoice-1.html`, the system generates:

- `output/invoice-1.xml` - UBL-compliant XML invoice
- `output/invoice-1.json` - Extracted and validated invoice data
- `output/extraction-report.json` - Comprehensive quality analysis for all invoices

### Example Console Output

```
Processing: invoice-1.html
Extracting invoice data from HTML...
Invoice extracted successfully

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📄 INVOICE DATA QUALITY REPORT: invoice-1.html
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🎯 OVERALL QUALITY SCORE: 82.4/100 🟢

📊 SECTION SCORES:
  📄 Invoice: 83.3/100 🟢
  🏢 Seller: 100.0/100 🟢
  👤 Buyer: 68.8/100 🟡
  📦 Lines: 100.0/100 🟢
  💳 Payment: 61.1/100 🟡

✅ UBL Validation: PASSED

💾 Files saved: invoice-1.xml, invoice-1.json
```

## Creating Invoices

### Method 1: Using HTML Input

1. **Create HTML Invoice**

   Save your invoice as an HTML file in `examples/`:

   ```html
   <!DOCTYPE html>
   <html>
   <body>
       <h1>Invoice #INV-2024-001</h1>
       <p>Date: 2024-11-24</p>
       
       <h2>From:</h2>
       <p>
           ACME Corporation<br>
           123 Main Street<br>
           Amsterdam, 1012AB<br>
           KVK: 12345678<br>
           VAT: NL123456789B01
       </p>
       
       <h2>To:</h2>
       <p>
           John Doe<br>
           john@example.com
       </p>
       
       <h2>Items:</h2>
       <table>
           <tr>
               <th>Description</th>
               <th>Quantity</th>
               <th>Price</th>
               <th>Total</th>
           </tr>
           <tr>
               <td>Widget Pro</td>
               <td>2</td>
               <td>€50.00</td>
               <td>€100.00</td>
           </tr>
       </table>
       
       <p>Subtotal: €100.00</p>
       <p>VAT (21%): €21.00</p>
       <p><strong>Total: €121.00</strong></p>
   </body>
   </html>
   ```

2. **Run the Generator**

   ```bash
   php generate.php
   ```

3. **Check Output**

   - `output/INV-2024-001.xml` - Your UBL XML invoice
   - `output/INV-2024-001.json` - Extracted data
   - `output/extraction-report.json` - Quality report

### Method 2: Programmatic Invoice Creation

Create a PHP script to build invoices programmatically:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dave\UblBuilder\InvoiceBuilder;
use Dave\UblBuilder\InvoiceValidator;
use Einvoicing\Writers\UblWriter;

// Define your invoice data
$invoiceData = [
    'invoice' => [
        'number' => 'INV-2024-001',
        'issueDate' => '2024-11-24',
        'dueDate' => '2024-12-24',
        'currency' => 'EUR'
    ],
    'seller' => [
        'name' => 'ACME Corporation',
        'address' => '123 Main Street',
        'city' => 'Amsterdam',
        'postalCode' => '1012AB',
        'country' => 'NL',
        'vatNumber' => 'NL123456789B01',
        'companyId' => '12345678',
        'companyIdScheme' => '0183',
        'electronicAddress' => 'sales@acme.com',
        'electronicAddressScheme' => '9957'
    ],
    'buyer' => [
        'name' => 'John Doe',
        'electronicAddress' => 'john@example.com',
        'electronicAddressScheme' => '9957'
    ],
    'lines' => [
        [
            'name' => 'Widget Pro',
            'quantity' => 2,
            'price' => 50.00,
            'vatRate' => 21,
            'vatCategory' => 'S'
        ]
    ],
    'totals' => [
        'taxExclusive' => 100.00,
        'taxInclusive' => 121.00,
        'taxAmount' => 21.00,
        'payableAmount' => 121.00
    ]
];

// Validate and apply fallbacks
$validator = new InvoiceValidator();
$validatedData = $validator->validate($invoiceData);

// Build UBL invoice
$builder = new InvoiceBuilder();
$invoice = $builder->build($validatedData);

// Validate UBL
$invoice->validate();
echo "✅ Invoice validated successfully!\n";

// Export to XML
$writer = new UblWriter();
$xml = $writer->export($invoice);
file_put_contents('my-invoice.xml', $xml);

echo "✅ Invoice saved to my-invoice.xml\n";
```

Run your script:

```bash
php my-invoice-script.php
```

## Invoice Data Structure

### Complete Data Schema

The data structure follows the [josemmo/einvoicing](https://github.com/josemmo/einvoicing) library structure exactly. All field names map directly to library methods.

```json
{
  "invoice": {
    "number": "INV-2025-001",
    "issueDate": "2025-11-25",
    "dueDate": "2025-12-25",
    "typeCode": "380",
    "buyerReference": "DEPT-001",
    "currency": "EUR"
  },
  "seller": {
    "name": "Example Supplier B.V.",
    "address": "Example Street 12",
    "city": "Amsterdam",
    "postalCode": "1000AA",
    "country": "NL",
    "vatNumber": "NL123456789B01",
    "companyId": "12345678",
    "companyIdScheme": "0106",
    "electronicAddress": "0106:12345678",
    "electronicAddressScheme": "0106",
    "contactName": "John Doe",
    "contactEmail": "info@example.com",
    "contactPhone": "+31 20 1234567"
  },
  "buyer": {
    "name": "Customer B.V.",
    "address": "Customer Road 56",
    "city": "Rotterdam",
    "postalCode": "3000BB",
    "country": "NL",
    "electronicAddress": "0106:87654321",
    "electronicAddressScheme": "0106",
    "vatNumber": "NL987654321B01",
    "companyId": "87654321",
    "companyIdScheme": "0106"
  },
  "lines": [
    {
      "id": 1,
      "name": "Consulting Services – November",
      "description": "Monthly consulting retainer",
      "quantity": 1,
      "unitCode": "H87",
      "price": 100.00,
      "vatRate": 21,
      "vatCategory": "S"
    }
  ],
  "totals": {
    "lineExtension": 150.00,
    "taxExclusive": 150.00,
    "taxAmount": 31.50,
    "taxInclusive": 181.50,
    "payableAmount": 181.50
  },
  "taxBreakdown": [
    {
      "rate": 21,
      "category": "S",
      "taxableAmount": 150.00,
      "taxAmount": 31.50
    }
  ],
  "payment": {
    "paymentMeansCode": "31",
    "iban": "NL00BANK0123456789",
    "bic": "BANKNL2A",
    "paymentTerms": "30 days net"
  }
}
```

### Field Mapping to Library

All fields map directly to the [josemmo/einvoicing](https://github.com/josemmo/einvoicing) library:

| Data Field | Library Method | Class |
|------------|----------------|-------|
| `invoice.number` | `setNumber()` | [`Invoice`](vendor/josemmo/einvoicing/src/Invoice.php) |
| `invoice.typeCode` | `setType()` | [`Invoice`](vendor/josemmo/einvoicing/src/Invoice.php) |
| `invoice.buyerReference` | `setBuyerReference()` | [`Invoice`](vendor/josemmo/einvoicing/src/Invoice.php) |
| `seller.companyId` | `setCompanyId(Identifier)` | [`Party`](vendor/josemmo/einvoicing/src/Party.php) |
| `lines[].id` | `setId()` | [`InvoiceLine`](vendor/josemmo/einvoicing/src/InvoiceLine.php) |
| `lines[].unitCode` | `setUnit()` | [`InvoiceLine`](vendor/josemmo/einvoicing/src/InvoiceLine.php) |
| `payment.paymentMeansCode` | `setMeansCode()` | [`Payment`](vendor/josemmo/einvoicing/src/Payments/Payment.php) |
| `payment.iban` | `setAccountId()` | [`Transfer`](vendor/josemmo/einvoicing/src/Payments/Transfer.php) |
| `payment.bic` | `setProvider()` | [`Transfer`](vendor/josemmo/einvoicing/src/Payments/Transfer.php) |

**Note:** Totals are automatically calculated by the library via [`InvoiceTotals::fromInvoice()`](vendor/josemmo/einvoicing/src/Models/InvoiceTotals.php).

### Invoice Type Codes

- `380` - Commercial Invoice (default)
- `381` - Credit Note
- `386` - Prepayment Invoice

### VAT Categories

- `S` - Standard rate (21% in NL)
- `Z` - Zero rated
- `E` - Exempt
- `AE` - Reverse charge

### Unit Codes (UN/ECE Recommendation 20)

- `H87` - Piece/Unit (default)
- `C62` - Unit (generic)
- `HUR` - Hour
- `DAY` - Day
- `MTR` - Meter
- `KGM` - Kilogram

### Electronic Address Schemes (ISO 6523)

- `0106` - Dutch KVK for PEPPOL (recommended for NL)
- `9957` - Email
- `0088` - EAN/GLN
- `0184` - Dutch Peppol ID

### Company ID Schemes (ISO 6523)

- `0106` - Dutch KVK for PEPPOL (recommended)
- `0183` - Dutch KVK (Chamber of Commerce)
- `9956` - Website registration

### Payment Means Codes

- `31` - SEPA Credit Transfer (default for NL)
- `30` - Credit Transfer
- `42` - Payment to bank account
- `48` - Bank card
- `49` - Direct debit

## Smart Fallbacks & Validation

When data is missing, the system applies legally compliant fallback values to ensure 100% UBL validation success:

| Missing Field | Fallback Value | Standard |
|--------------|----------------|----------|
| Buyer address | "Teststraat 1" | Test address |
| Buyer city | "Amsterdam" | Valid city |
| Buyer postal code | "1000AA" | Valid NL format (non-existent) |
| Buyer email | "noreply@buyer.invalid" | RFC 6761 (.invalid TLD) |
| Email scheme | "9957" | ISO 6523 (email) |
| Company ID scheme | "0106" | ISO 6523 (NL KVK PEPPOL) |
| Payment means code | "31" | SEPA Credit Transfer |
| IBAN | "NL00INGB0000000000" | Valid format, invalid checksum |
| BIC | "INGBNL2A" | ISO 9362 format |

All fallback data:
- ✅ Passes UBL validation
- ✅ Follows international standards
- ✅ Won't interfere with real systems
- ✅ Is clearly tracked in reports

---

# Analyze: Quality Reporting

The system provides comprehensive quality analysis for all extracted and generated invoices.

## Understanding Quality Scores

## Report Types

### 1. Console Reports

Each invoice displays a real-time console report showing:
- Overall quality score (0-100)
- Section-by-section breakdown
- List of dummy fields used
- Originally missing fields
- Warnings and validation messages

### 2. JSON Report

`output/extraction-report.json` contains:

```json
{
  "summary": {
    "totalInvoices": 5,
    "averageScore": 79.1,
    "perfectScores": 0,
    "needsImprovement": 3,
    "sectionAverages": {
      "invoice": 83.3,
      "seller": 97.8,
      "buyer": 71.9,
      "lines": 88.3,
      "payment": 36.7
    }
  },
  "invoices": [
    {
      "file": "invoice-1",
      "invoiceNumber": "INV-2024-001",
      "score": 82.4,
      "sections": {...},
      "dummyFields": [...],
      "missingFields": [...],
      "warnings": [...]
    }
  ]
}
```

## Score Interpretation Guide

- **90-100**: 🟢 Excellent - Ready for production
- **70-89**: 🟡 Good - Minor improvements recommended
- **50-69**: 🟠 Fair - Consider enhancing data extraction
- **0-49**: 🔴 Poor - Significant improvements needed

## Analyzing Your Results

Use the JSON report to identify improvement areas:

1. **Check Overall Score** - Target 90+ for production
2. **Review Section Scores** - Identify weak areas (buyer, payment, etc.)
3. **Examine Dummy Fields** - These indicate missing data in source
4. **Read Warnings** - Address validation concerns
5. **Compare Invoices** - Find patterns in low-scoring invoices

## Improving Quality Scores

### For Scores Below 70

1. Review `output/extraction-report.json` for missing fields
2. Improve HTML structure in source files
3. Switch from `gpt-4o-mini` to `gpt-4o` for better extraction
4. Add more context to HTML (labels, sections, clear structure)

### For Consistent Low Scores in Specific Sections

- **Buyer Section**: Ensure buyer details are clearly separated from seller
- **Payment Section**: Add IBAN/BIC in a dedicated payment section
- **Invoice Section**: Use clear labels for invoice number and dates
- **Lines Section**: Use HTML tables for line items

---

# Installation & Setup

## Requirements

- PHP 8.0 or higher
- Composer
- OpenAI API key

## Installation Steps

### 1. Clone the Repository

```bash
git clone <repository-url>
cd ubl-builder
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Create a `.env` file in the project root:

```bash
cp .env.example .env
```

Edit `.env` and add your OpenAI API key:

```env
OPENAI_API_KEY=sk-your-api-key-here
OPENAI_MODEL=gpt-4o-mini  # or gpt-4o for better accuracy
```

## Project Structure

```
ubl-builder/
├── src/
│   ├── OpenApiClient.php        # OpenAI API client
│   ├── InvoiceExtractor.php     # HTML to JSON extraction
│   ├── InvoiceValidator.php     # Smart fallbacks & validation
│   ├── InvoiceBuilder.php       # JSON to UBL object builder
│   └── DataQualityAnalyzer.php  # Quality scoring & reporting
├── examples/                     # Input HTML invoice files
├── output/                       # Generated XML, JSON, and reports
├── generate.php                  # Main generation script
├── composer.json
├── .env                         # API configuration (not in repo)
└── README.md
```

---

# Reference

## Troubleshooting

### OpenAI API Errors

**Error:** "Invalid API key"
```bash
# Verify your API key in .env
echo $OPENAI_API_KEY  # Should show your key
```

**Error:** "Rate limit exceeded"
- Wait a few minutes and retry
- Consider upgrading your OpenAI plan
- Process fewer invoices at once

### Validation Errors

**Error:** "Invoice validation failed"
- Check the console output for specific validation messages
- Review the JSON output for missing or invalid data
- Ensure dates are in YYYY-MM-DD format
- Verify VAT numbers follow country-specific formats

### Empty Output

**Issue:** No XML files generated
```bash
# Check permissions
chmod 755 output/
ls -la output/

# Verify HTML files exist
ls -la examples/
```

## Configuration

### Using Different OpenAI Models

Edit `.env`:

```env
# Better accuracy, higher cost
OPENAI_MODEL=gpt-4o

# Faster, lower cost
OPENAI_MODEL=gpt-4o-mini
```

### Custom Validation Rules

Edit `src/InvoiceValidator.php` to customize:
- Fallback values
- Validation logic
- B2B/B2C detection rules

### Custom Quality Scoring

Edit `src/DataQualityAnalyzer.php` to adjust:
- Field weights
- Section importance
- Score thresholds

## Best Practices

### For Best Extraction Results

1. **Well-Structured HTML**: Use semantic HTML with clear sections
2. **Clear Labels**: Mark invoice numbers, dates, parties clearly
3. **Tabular Data**: Use tables for line items
4. **Complete Information**: Include all parties, amounts, and dates
5. **Standard Formats**: Use consistent date and number formats

### For Production Use

1. **Validate Before Sending**: Always validate generated UBL XML
2. **Monitor Quality**: Review extraction reports regularly
3. **Handle Errors**: Implement retry logic for API failures
4. **Secure API Keys**: Never commit `.env` to version control
5. **Test Thoroughly**: Validate with your e-invoicing platform

## API Costs & Pricing

Typical costs with OpenAI (as of 2024):

| Model | Input | Output | Est. per Invoice |
|-------|--------|--------|------------------|
| gpt-4o-mini | $0.15/1M tokens | $0.60/1M tokens | ~$0.01 |
| gpt-4o | $2.50/1M tokens | $10.00/1M tokens | ~$0.15 |

## License

MIT License - See LICENSE file for details

## Support

For issues, questions, or contributions:
1. Check the troubleshooting section above
2. Review existing issues
3. Open a new issue with details

## Changelog

### v1.0.0 (2024-11-24)
- ✅ Initial release
- ✅ 100% UBL validation success
- ✅ Smart fallback system
- ✅ Comprehensive quality reporting
- ✅ Support for B2B and B2C invoices

## Credits

Built with:
- [josemmo/einvoicing](https://github.com/josemmo/einvoicing) - PHP library for e-invoicing
- [OpenAI API](https://openai.com/) - AI-powered data extraction
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) - Environment configuration

---

Made with ❤️ for the e-invoicing community
