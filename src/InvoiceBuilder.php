<?php

namespace Dave\UblBuilder;

use DateTime;
use Einvoicing\Invoice;
use Einvoicing\InvoiceLine;
use Einvoicing\Party;
use Einvoicing\Identifier;
use Einvoicing\Presets;
use Exception;

/**
 * Invoice Builder
 * 
 * Builds eInvoicing objects from extracted invoice data
 */
class InvoiceBuilder
{
    private string $preset;

    /**
     * Constructor
     *
     * @param string $preset Preset class to use (default: Peppol)
     */
    public function __construct(string $preset = Presets\Peppol::class)
    {
        $this->preset = $preset;
    }

    /**
     * Build Invoice object from extracted data
     *
     * @param array $data Extracted invoice data
     * @return Invoice Invoice object
     * @throws Exception If building fails
     */
    public function build(array $data): Invoice
    {
        // Create invoice instance with preset
        $invoice = new Invoice($this->preset);

        // Set basic invoice information
        $this->setInvoiceInfo($invoice, $data['invoice']);

        // Set seller party
        $seller = $this->buildParty($data['seller']);
        $invoice->setSeller($seller);

        // Set buyer party
        $buyer = $this->buildParty($data['buyer']);
        $invoice->setBuyer($buyer);

        // Add invoice lines
        foreach ($data['lines'] as $lineData) {
            $line = $this->buildInvoiceLine($lineData);
            $invoice->addLine($line);
        }

        // Set payment information if available
        if (isset($data['paymentInfo'])) {
            $this->setPaymentInfo($invoice, $data['paymentInfo']);
        }

        return $invoice;
    }

    /**
     * Set invoice information
     *
     * @param Invoice $invoice Invoice instance
     * @param array $data Invoice data
     * @throws Exception If date parsing fails
     */
    private function setInvoiceInfo(Invoice $invoice, array $data): void
    {
        $invoice->setNumber($data['number']);

        // Parse issue date
        $issueDate = DateTime::createFromFormat('Y-m-d', $data['issueDate']);
        if ($issueDate === false) {
            throw new Exception("Invalid issue date format: {$data['issueDate']}");
        }
        $invoice->setIssueDate($issueDate);

        // Parse due date if available
        if (!empty($data['dueDate'])) {
            $dueDate = DateTime::createFromFormat('Y-m-d', $data['dueDate']);
            if ($dueDate === false) {
                throw new Exception("Invalid due date format: {$data['dueDate']}");
            }
            $invoice->setDueDate($dueDate);
        }

        // Set currency (default to EUR)
        $currency = $data['currency'] ?? 'EUR';
        $invoice->setCurrency($currency);

        // Set notes if available
        if (!empty($data['notes'])) {
            $invoice->addNote($data['notes']);
        }

        // Set buyer reference or purchase order reference (required by validation)
        // Use buyer reference if provided
        if (!empty($data['buyerReference'])) {
            $invoice->setBuyerReference($data['buyerReference']);
        }
        // Otherwise use purchase order reference if provided
        elseif (!empty($data['purchaseOrderReference'])) {
            $invoice->setPurchaseOrderReference($data['purchaseOrderReference']);
        }
        // If neither is provided, use invoice number as placeholder
        else {
            $invoice->setBuyerReference($data['number']);
        }
    }

    /**
     * Build Party object from data
     *
     * @param array $data Party data
     * @return Party Party object
     */
    private function buildParty(array $data): Party
    {
        $party = new Party();

        // Set name (required)
        $party->setName($data['name']);

        // Set trading name if different
        if (!empty($data['tradingName']) && $data['tradingName'] !== $data['name']) {
            $party->setTradingName($data['tradingName']);
        }

        // Set electronic address
        if (!empty($data['electronicAddress'])) {
            $scheme = $data['electronicAddressScheme'] ?? '0088'; // Default to GLN
            $party->setElectronicAddress(new Identifier($data['electronicAddress'], $scheme));
        }

        // Set company ID
        if (!empty($data['companyId'])) {
            // Default to Dutch KVK scheme if no scheme specified
            $scheme = $data['companyIdScheme'] ?? '0183';
            $party->setCompanyId(new Identifier($data['companyId'], $scheme));
        }

        // Set VAT number
        if (!empty($data['vatNumber'])) {
            $party->setVatNumber($data['vatNumber']);
        }

        // Set address
        if (!empty($data['address'])) {
            $addressLines = is_array($data['address']) ? $data['address'] : [$data['address']];
            $party->setAddress($addressLines);
        }

        // Set city
        if (!empty($data['city'])) {
            $party->setCity($data['city']);
        }

        // Set postal code
        if (!empty($data['postalCode'])) {
            $party->setPostalCode($data['postalCode']);
        }

        // Set country (required)
        if (!empty($data['country'])) {
            $party->setCountry($data['country']);
        }

        // Set contact details
        if (!empty($data['contactName'])) {
            $party->setContactName($data['contactName']);
        }
        if (!empty($data['contactPhone'])) {
            $party->setContactPhone($data['contactPhone']);
        }
        if (!empty($data['contactEmail'])) {
            $party->setContactEmail($data['contactEmail']);
        }

        return $party;
    }

    /**
     * Build InvoiceLine object from data
     *
     * @param array $data Line data
     * @return InvoiceLine InvoiceLine object
     */
    private function buildInvoiceLine(array $data): InvoiceLine
    {
        $line = new InvoiceLine();

        // Set name (required)
        $line->setName($data['name']);

        // Set description if available
        if (!empty($data['description'])) {
            $line->setDescription($data['description']);
        }

        // Set quantity (required)
        $line->setQuantity($data['quantity']);

        // Set unit if available
        if (!empty($data['unit'])) {
            $line->setUnit($data['unit']);
        }

        // Set price (required)
        // If priceQuantity is specified, use it (price per X units)
        if (isset($data['priceQuantity']) && $data['priceQuantity'] > 1) {
            $line->setPrice($data['price'], $data['priceQuantity']);
        } else {
            $line->setPrice($data['price']);
        }

        // Set VAT rate if available
        if (isset($data['vatRate'])) {
            $line->setVatRate($data['vatRate']);
        }

        // Set VAT category if available
        if (!empty($data['vatCategory'])) {
            $line->setVatCategory($data['vatCategory']);
        }

        return $line;
    }

    /**
     * Set payment information
     *
     * @param Invoice $invoice Invoice instance
     * @param array $data Payment data
     */
    private function setPaymentInfo(Invoice $invoice, array $data): void
    {
        // Payment means methods not available in current version of library
        // TODO: Add payment information when library supports it
        
        // Set payment terms if available
        if (!empty($data['paymentTerms'])) {
            $invoice->setPaymentTerms($data['paymentTerms']);
        }
    }

    /**
     * Set preset to use
     *
     * @param string $preset Preset class
     */
    public function setPreset(string $preset): void
    {
        $this->preset = $preset;
    }

    /**
     * Get current preset
     *
     * @return string Preset class
     */
    public function getPreset(): string
    {
        return $this->preset;
    }
}
