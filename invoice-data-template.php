<?php

/**
 * Complete Invoice Data Array Template
 * 
 * This template shows the complete structure for invoice data that can be used
 * with the UBL Invoice Builder. All field names map directly to the 
 * josemmo/einvoicing library methods.
 * 
 * @see https://github.com/josemmo/einvoicing
 * @see vendor/josemmo/einvoicing/src/Invoice.php
 * @see vendor/josemmo/einvoicing/src/Party.php
 * @see vendor/josemmo/einvoicing/src/InvoiceLine.php
 */

$invoiceData = [

    // --------------------------
    // BASIC INVOICE INFORMATION
    // --------------------------
    'invoice' => [
        // REQUIRED – Unique invoice number
        'number' => 'INV-2025-001',
        
        // REQUIRED – Invoice date in YYYY-MM-DD format
        'issueDate' => '2025-11-25',
        
        // REQUIRED – Payment due date in YYYY-MM-DD format
        'dueDate' => '2025-12-25',
        
        // REQUIRED – Invoice type code
        // Common values:
        //   380 = Commercial Invoice (default)
        //   381 = Credit Note
        //   386 = Prepayment Invoice
        //   383 = Debit Note
        'typeCode' => '380',

        // OPTIONAL – Buyer reference or purchase order number
        // Required by many NL government buyers
        // If not provided, invoice number will be used as fallback
        'buyerReference' => 'DEPT-001',
        
        // REQUIRED – ISO currency code
        // Common values: EUR, USD, GBP
        // Default: EUR
        'currency' => 'EUR',
        
        // OPTIONAL – Additional notes or comments
        'notes' => 'Payment terms: 30 days net'
    ],

    // --------------------------
    // SELLER INFORMATION
    // --------------------------
    'seller' => [
        // REQUIRED – Seller legal name
        'name' => 'Example Supplier B.V.',
        
        // OPTIONAL – Trading name if different from legal name
        'tradingName' => 'Example Supplier',
        
        // REQUIRED – Street address
        'address' => 'Example Street 12',
        
        // REQUIRED – City name
        'city' => 'Amsterdam',
        
        // REQUIRED – Postal/ZIP code
        'postalCode' => '1000AA',
        
        // REQUIRED – Two-letter ISO country code
        // Common values: NL, DE, BE, FR, GB, US
        // Default: NL
        'country' => 'NL',

        // REQUIRED – Seller VAT identification number
        // Format varies by country:
        //   NL: NL123456789B01 (NL + 9 digits + B + 2 digits)
        //   DE: DE123456789 (DE + 9 digits)
        //   BE: BE0123456789 (BE + 10 digits)
        'vatNumber' => 'NL123456789B01',
        
        // REQUIRED – Company registration number (e.g., Dutch KVK number)
        'companyId' => '12345678',
        
        // REQUIRED – Scheme for company ID
        // Common values:
        //   0106 = Dutch KVK for PEPPOL (recommended for NL)
        //   0183 = Dutch KVK (Chamber of Commerce)
        //   9956 = Website registration
        // Default: 0106
        'companyIdScheme' => '0106',

        // REQUIRED – Seller's PEPPOL participant ID or electronic address
        // For NL companies, typically "0106:KVKNUMBER"
        // Can also be email, GLN, etc.
        'electronicAddress' => '0106:12345678',
        
        // REQUIRED – Scheme code for electronic address
        // Common values:
        //   0106 = Dutch KVK for PEPPOL (recommended for NL)
        //   9957 = Email
        //   0088 = EAN/GLN
        //   0184 = Dutch Peppol ID
        // Default: 0106
        'electronicAddressScheme' => '0106',

        // OPTIONAL but recommended – Contact person name
        'contactName' => 'John Doe',
        
        // OPTIONAL but recommended – Contact email address
        'contactEmail' => 'info@example.com',
        
        // OPTIONAL but recommended – Contact phone number
        'contactPhone' => '+31 20 1234567'
    ],

    // --------------------------
    // BUYER INFORMATION
    // --------------------------
    'buyer' => [
        // REQUIRED – Buyer company or person name
        'name' => 'Customer B.V.',

        // OPTIONAL – Buyer postal address (recommended if available)
        // If missing, fallback "Teststraat 1" will be used
        'address' => 'Customer Road 56',
        
        // OPTIONAL – Buyer city
        // If missing, fallback "Amsterdam" will be used
        'city' => 'Rotterdam',
        
        // OPTIONAL – Buyer postal code
        // If missing, fallback "1000AA" will be used
        'postalCode' => '3000BB',
        
        // OPTIONAL – Buyer country code
        // Default: NL
        'country' => 'NL',

        // REQUIRED – Buyer's PEPPOL ID or electronic address
        // If missing, fallback email will be used
        'electronicAddress' => '0106:87654321',
        
        // REQUIRED – Scheme code for buyer's electronic address
        // If missing, fallback to 0106 or 9957 (email)
        'electronicAddressScheme' => '0106',

        // OPTIONAL – Buyer VAT number (required for B2B, optional for B2C)
        'vatNumber' => 'NL987654321B01',
        
        // OPTIONAL – Buyer company ID (required for B2B, optional for B2C)
        'companyId' => '87654321',
        
        // OPTIONAL – Scheme for buyer's company ID
        // Default: 0106
        'companyIdScheme' => '0106',
        
        // OPTIONAL – Buyer contact name
        'contactName' => 'Jane Smith',
        
        // OPTIONAL – Buyer reference or cost center
        'reference' => 'PROJ-2025-001'
    ],

    // --------------------------
    // INVOICE LINES (PRODUCTS/SERVICES)
    // --------------------------
    'lines' => [
        [
            // REQUIRED – Unique line identifier (1, 2, 3, etc.)
            // Auto-generated if not provided
            'id' => 1,
            
            // REQUIRED – Product or service name
            'name' => 'Consulting Services – November',
            
            // OPTIONAL – Additional description
            'description' => 'Monthly consulting retainer for project management',

            // REQUIRED – Quantity
            'quantity' => 1,
            
            // REQUIRED – Unit of measure code (UN/ECE Recommendation 20)
            // Common values:
            //   H87 = Piece/Unit (default)
            //   C62 = Unit (generic)
            //   HUR = Hour
            //   DAY = Day
            //   MTR = Meter
            //   KGM = Kilogram
            //   LTR = Liter
            // Default: H87
            'unitCode' => 'H87',

            // REQUIRED – Price per unit (excluding VAT)
            'price' => 100.00,
            
            // OPTIONAL – Base quantity for price (default: 1)
            // Use when price is per multiple units (e.g., price per 100 pieces)
            'priceQuantity' => 1,

            // REQUIRED – VAT rate as integer percentage
            // Common NL rates: 21 (high), 9 (low), 0 (zero-rated)
            // Default: 21
            'vatRate' => 21,
            
            // REQUIRED – VAT category code
            // Values:
            //   S = Standard rate (21% in NL)
            //   Z = Zero rated (0%)
            //   E = Exempt from VAT
            //   AE = Reverse charge
            // Default: S
            'vatCategory' => 'S',
            
            // OPTIONAL – Net amount for line (calculated: quantity × price)
            // Usually calculated automatically
            'netAmount' => 100.00
        ],
        
        // Additional line example
        [
            'id' => 2,
            'name' => 'Hosting Fees',
            'description' => 'Monthly server hosting',
            'quantity' => 1,
            'unitCode' => 'H87',
            'price' => 50.00,
            'vatRate' => 21,
            'vatCategory' => 'S'
        ]
    ],

    // --------------------------
    // TOTALS (CALCULATED)
    // --------------------------
    // Note: These are typically calculated automatically by the library
    // via InvoiceTotals::fromInvoice(), but can be provided for validation
    'totals' => [
        // Sum of all line amounts excluding VAT
        'lineExtension' => 150.00,
        
        // Total amount excluding VAT (same as lineExtension for simple invoices)
        'taxExclusive' => 150.00,
        
        // Total VAT amount
        'taxAmount' => 31.50,
        
        // Total amount including VAT
        'taxInclusive' => 181.50,
        
        // Amount to be paid (usually same as taxInclusive)
        'payableAmount' => 181.50
    ],

    // --------------------------
    // VAT BREAKDOWN (PER RATE)
    // --------------------------
    // Note: Automatically calculated by the library, but can be provided
    'taxBreakdown' => [
        [
            // REQUIRED – VAT rate as integer
            'rate' => 21,
            
            // REQUIRED – VAT category code (S, Z, E, AE)
            'category' => 'S',
            
            // REQUIRED – Base amount for this VAT rate
            'taxableAmount' => 150.00,
            
            // REQUIRED – VAT amount for this rate
            'taxAmount' => 31.50
        ]
        // Add more entries for multiple VAT rates
    ],

    // --------------------------
    // PAYMENT INFORMATION
    // --------------------------
    'payment' => [
        // REQUIRED – Payment means code
        // Common values:
        //   31 = SEPA Credit Transfer (default for NL)
        //   30 = Credit Transfer
        //   42 = Payment to bank account
        //   48 = Bank card
        //   49 = Direct debit
        //   58 = SEPA Direct Debit
        // Default: 31
        'paymentMeansCode' => '31',
        
        // REQUIRED – Seller's IBAN
        // Format: 2-letter country code + 2 check digits + up to 30 alphanumeric
        // Example NL: NL91ABNA0417164300
        'iban' => 'NL00BANK0123456789',
        
        // OPTIONAL – Bank BIC/SWIFT code
        // Required for cross-border payments, optional for NL → NL
        // Format: 8 or 11 characters (e.g., ABNANL2A or ABNANL2AXXX)
        'bic' => 'BANKNL2A',
        
        // OPTIONAL – Payment terms description
        // Free text field for human-readable payment terms
        'paymentTerms' => '30 days net'
    ]
];

// --------------------------
// USAGE EXAMPLE
// --------------------------

/*
require_once __DIR__ . '/vendor/autoload.php';

use Dave\UblBuilder\InvoiceBuilder;
use Dave\UblBuilder\InvoiceValidator;
use Einvoicing\Writers\UblWriter;

// Validate and apply smart fallbacks
$validator = new InvoiceValidator();
$validator->applyIntelligentFallbacks($invoiceData);

// Build UBL invoice
$builder = new InvoiceBuilder();
$invoice = $builder->build($invoiceData);

// Validate UBL compliance
$invoice->validate();

// Export to XML
$writer = new UblWriter();
$xml = $writer->export($invoice);
file_put_contents('invoice.xml', $xml);
*/

// --------------------------
// MINIMAL REQUIRED EXAMPLE
// --------------------------

$minimalInvoice = [
    'invoice' => [
        'number' => 'INV-2025-001',
        'issueDate' => '2025-11-25',
        'dueDate' => '2025-12-25',
        'typeCode' => '380',
        'currency' => 'EUR'
    ],
    'seller' => [
        'name' => 'My Company B.V.',
        'address' => 'Main Street 1',
        'city' => 'Amsterdam',
        'postalCode' => '1012AB',
        'country' => 'NL',
        'vatNumber' => 'NL123456789B01',
        'companyId' => '12345678',
        'companyIdScheme' => '0106',
        'electronicAddress' => '0106:12345678',
        'electronicAddressScheme' => '0106'
    ],
    'buyer' => [
        'name' => 'Customer Name',
        'electronicAddress' => 'customer@example.com',
        'electronicAddressScheme' => '9957'
    ],
    'lines' => [
        [
            'id' => 1,
            'name' => 'Product or Service',
            'quantity' => 1,
            'unitCode' => 'H87',
            'price' => 100.00,
            'vatRate' => 21,
            'vatCategory' => 'S'
        ]
    ],
    'payment' => [
        'paymentMeansCode' => '31',
        'iban' => 'NL91ABNA0417164300'
    ]
];

// Smart fallbacks will automatically fill in:
// - buyer.address, buyer.city, buyer.postalCode, buyer.country
// - invoice.buyerReference (uses invoice number)
// - payment.bic (if needed)
// - totals (calculated from lines)
// - taxBreakdown (calculated from lines)