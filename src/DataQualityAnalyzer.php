<?php

namespace Dave\UblBuilder;

/**
 * Data Quality Analyzer
 * 
 * Analyzes invoice data quality, calculates completeness scores,
 * and generates detailed reports
 */
class DataQualityAnalyzer
{
    /**
     * Calculate comprehensive data completeness score
     *
     * @param array $data Extracted invoice data
     * @return array Score details with sections, missing fields, and warnings
     */
    public function calculateCompletenessScore(array $data): array
    {
        $scores = [
            'sections' => [
                'invoice' => $this->scoreInvoiceSection($data['invoice']),
                'seller' => $this->scorePartySection($data['seller'], 'seller'),
                'buyer' => $this->scorePartySection($data['buyer'], 'buyer'),
                'lines' => $this->scoreLinesSection($data['lines']),
                'payment' => $this->scorePaymentSection($data['paymentInfo'] ?? []),
            ],
            'dummyFields' => $data['_metadata']['dummyFields'] ?? [],
            'missingFields' => $data['_metadata']['missingFields'] ?? [],
            'warnings' => $data['_metadata']['warnings'] ?? []
        ];

        // Calculate overall score (weighted average)
        $scores['overall'] = round(
            $scores['sections']['invoice']['score'] * 0.15 +  // 15%
            $scores['sections']['seller']['score'] * 0.25 +   // 25%
            $scores['sections']['buyer']['score'] * 0.30 +    // 30% (most important)
            $scores['sections']['lines']['score'] * 0.20 +    // 20%
            $scores['sections']['payment']['score'] * 0.10,   // 10%
            1
        );

        return $scores;
    }

    /**
     * Score invoice section
     *
     * @param array $invoice Invoice data
     * @return array Score details
     */
    private function scoreInvoiceSection(array $invoice): array
    {
        $criticalFields = ['number', 'issueDate', 'currency'];
        $importantFields = ['dueDate', 'notes'];
        
        return $this->scoreFields($invoice, $criticalFields, $importantFields, [], 'invoice');
    }

    /**
     * Score party section (seller/buyer)
     *
     * @param array $party Party data
     * @param string $type 'seller' or 'buyer'
     * @return array Score details
     */
    private function scorePartySection(array $party, string $type): array
    {
        $criticalFields = ['name', 'address', 'city', 'country', 'electronicAddress'];
        $importantFields = ['postalCode', 'vatNumber', 'companyId'];
        $optionalFields = ['contactName', 'contactEmail', 'contactPhone'];
        
        return $this->scoreFields($party, $criticalFields, $importantFields, $optionalFields, $type);
    }

    /**
     * Score lines section
     *
     * @param array $lines Invoice lines
     * @return array Score details
     */
    private function scoreLinesSection(array $lines): array
    {
        if (empty($lines)) {
            return ['score' => 0, 'present' => [], 'missing' => ['lines'], 'total_fields' => 1];
        }

        $hasDetailedLines = count($lines) > 1;
        $hasDescriptions = array_filter($lines, fn($l) => !empty($l['description']));
        $hasProperPricing = array_filter($lines, fn($l) => isset($l['price']) && isset($l['quantity']));
        
        $score = 0;
        $score += $hasDetailedLines ? 40 : 20; // Multiple lines better than single
        $score += (count($hasDescriptions) > 0) ? 30 : 10; // Descriptions add clarity
        $score += (count($hasProperPricing) === count($lines)) ? 30 : 15; // All lines have pricing
        
        $missing = [];
        if (!$hasDetailedLines) {
            $missing[] = 'lines.detail (only 1 line, may be aggregated)';
        }
        if (count($hasDescriptions) === 0) {
            $missing[] = 'lines.descriptions';
        }

        return [
            'score' => $score,
            'present' => [
                'lineCount' => count($lines),
                'withDescriptions' => count($hasDescriptions),
                'withPricing' => count($hasProperPricing)
            ],
            'missing' => $missing,
            'total_fields' => 3
        ];
    }

    /**
     * Score payment section
     *
     * @param array $payment Payment info
     * @return array Score details
     */
    private function scorePaymentSection(array $payment): array
    {
        $importantFields = ['iban', 'bic', 'accountName'];
        $optionalFields = ['paymentReference', 'paymentTerms'];
        
        return $this->scoreFields($payment, [], $importantFields, $optionalFields, 'paymentInfo');
    }

    /**
     * Generic field scoring logic
     *
     * @param array $data Data to score
     * @param array $criticalFields Critical field names
     * @param array $importantFields Important field names
     * @param array $optionalFields Optional field names
     * @param string $prefix Prefix for missing field names
     * @return array Score details
     */
    private function scoreFields(
        array $data,
        array $criticalFields,
        array $importantFields,
        array $optionalFields,
        string $prefix
    ): array {
        $present = [
            'critical' => 0,
            'important' => 0,
            'optional' => 0
        ];
        
        $missing = [];
        
        // Score critical fields (60% weight)
        foreach ($criticalFields as $field) {
            if (!empty($data[$field])) {
                $present['critical']++;
            } else {
                $missing[] = "$prefix.$field";
            }
        }
        
        // Score important fields (30% weight)
        foreach ($importantFields as $field) {
            if (!empty($data[$field])) {
                $present['important']++;
            } else {
                $missing[] = "$prefix.$field";
            }
        }
        
        // Score optional fields (10% weight)
        foreach ($optionalFields as $field) {
            if (!empty($data[$field])) {
                $present['optional']++;
            }
        }
        
        // Calculate weighted score
        $score = 0;
        if (!empty($criticalFields)) {
            $score += ($present['critical'] / count($criticalFields)) * 60;
        }
        if (!empty($importantFields)) {
            $score += ($present['important'] / count($importantFields)) * 30;
        }
        if (!empty($optionalFields)) {
            $score += ($present['optional'] / count($optionalFields)) * 10;
        }
        
        // If no fields defined, score based on what we have
        $totalFields = count($criticalFields) + count($importantFields) + count($optionalFields);
        if ($totalFields === 0) {
            $score = 100;
        }
        
        return [
            'score' => round($score, 1),
            'present' => $present,
            'missing' => $missing,
            'total_fields' => $totalFields
        ];
    }

    /**
     * Generate formatted console report
     *
     * @param string $filename Source filename
     * @param array $invoiceData Invoice data with metadata
     * @param array $completeness Completeness scores
     * @return string Formatted report
     */
    public function generateConsoleReport(string $filename, array $invoiceData, array $completeness): string
    {
        $output = [];
        
        $output[] = "\n" . str_repeat('=', 70);
        $output[] = "Processing: {$filename}";
        $output[] = str_repeat('=', 70);
        
        $output[] = "\n✅ EXTRACTION COMPLETE";
        $output[] = "Invoice: {$invoiceData['invoice']['number']}";
        
        // Overall score with color coding
        $score = $completeness['overall'];
        $scoreEmoji = $score >= 80 ? '🟢' : ($score >= 60 ? '🟡' : '🔴');
        $output[] = "\nOverall Quality Score: {$scoreEmoji} {$score}/100";
        
        // Section scores
        $output[] = "\nSection Breakdown:";
        $output[] = "  📄 Invoice:  " . $completeness['sections']['invoice']['score'] . "/100";
        $output[] = "  🏢 Seller:   " . $completeness['sections']['seller']['score'] . "/100";
        $output[] = "  👤 Buyer:    " . $completeness['sections']['buyer']['score'] . "/100";
        $output[] = "  📋 Lines:    " . $completeness['sections']['lines']['score'] . "/100";
        $output[] = "  💰 Payment:  " . $completeness['sections']['payment']['score'] . "/100";
        
        // Dummy data report
        if (!empty($completeness['dummyFields'])) {
            $count = count($completeness['dummyFields']);
            $output[] = "\n⚠️  DUMMY DATA USED ({$count} fields):";
            foreach ($completeness['dummyFields'] as $field) {
                $output[] = "  • $field";
            }
        }
        
        // Missing fields report
        if (!empty($completeness['missingFields'])) {
            $count = count($completeness['missingFields']);
            $output[] = "\n❌ ORIGINALLY MISSING ({$count} fields):";
            foreach ($completeness['missingFields'] as $field) {
                $output[] = "  • $field";
            }
        }
        
        // Warnings
        if (!empty($completeness['warnings'])) {
            $output[] = "\n⚠️  WARNINGS:";
            foreach ($completeness['warnings'] as $warning) {
                $output[] = "  • $warning";
            }
        }
        
        // Detailed missing fields by section
        $output[] = "\n📊 DETAILED ANALYSIS:";
        $hasDetails = false;
        foreach ($completeness['sections'] as $sectionName => $section) {
            if (!empty($section['missing'])) {
                $hasDetails = true;
                $output[] = "\n  {$sectionName} - Missing fields:";
                foreach ($section['missing'] as $field) {
                    $output[] = "    • $field";
                }
            }
        }
        
        if (!$hasDetails) {
            $output[] = "  ✅ All expected fields present!";
        }
        
        $output[] = "\n" . str_repeat('-', 70);
        
        return implode("\n", $output);
    }

    /**
     * Generate summary report for multiple invoices
     *
     * @param array $results Array of results from multiple invoice extractions
     * @return string Formatted summary
     */
    public function generateSummaryReport(array $results): string
    {
        $output = [];
        
        $output[] = "\n" . str_repeat('=', 70);
        $output[] = "PROCESSING SUMMARY";
        $output[] = str_repeat('=', 70);
        
        $totalInvoices = count($results);
        $totalScore = array_sum(array_column($results, 'score'));
        $avgScore = $totalInvoices > 0 ? round($totalScore / $totalInvoices, 1) : 0;
        
        $withDummy = array_filter($results, fn($r) => !empty($r['dummyFields']));
        $dummyCount = count($withDummy);
        
        $totalDummyFields = array_sum(array_map(fn($r) => count($r['dummyFields'] ?? []), $results));
        $avgDummy = $totalInvoices > 0 ? round($totalDummyFields / $totalInvoices, 1) : 0;
        
        $output[] = "Total Invoices Processed: {$totalInvoices}";
        $output[] = "Average Quality Score: {$avgScore}/100";
        $output[] = "Invoices with Dummy Data: {$dummyCount} (" . round(($dummyCount/$totalInvoices)*100, 1) . "%)";
        $output[] = "Average Dummy Fields per Invoice: {$avgDummy}";
        
        // Most common missing fields
        $allMissing = [];
        foreach ($results as $result) {
            foreach ($result['missingFields'] ?? [] as $field) {
                $allMissing[$field] = ($allMissing[$field] ?? 0) + 1;
            }
        }
        
        if (!empty($allMissing)) {
            arsort($allMissing);
            $output[] = "\nMost Common Missing Fields:";
            $topMissing = array_slice($allMissing, 0, 5, true);
            foreach ($topMissing as $field => $count) {
                $pct = round(($count / $totalInvoices) * 100, 1);
                $output[] = "  • {$field} ({$count} invoices, {$pct}%)";
            }
        }
        
        $output[] = str_repeat('=', 70);
        
        return implode("\n", $output);
    }

    /**
     * Generate detailed JSON report
     *
     * @param array $results Array of results from multiple invoice extractions
     * @return array Report data structure
     */
    public function generateDetailedReport(array $results): array
    {
        $totalInvoices = count($results);
        $totalScore = array_sum(array_column($results, 'score'));
        $avgScore = $totalInvoices > 0 ? round($totalScore / $totalInvoices, 1) : 0;
        
        $withDummy = array_filter($results, fn($r) => !empty($r['dummyFields']));
        $totalDummyFields = array_sum(array_map(fn($r) => count($r['dummyFields'] ?? []), $results));
        
        // Collect all missing fields across invoices
        $missingFieldStats = [];
        foreach ($results as $result) {
            foreach ($result['missingFields'] ?? [] as $field) {
                $missingFieldStats[$field] = ($missingFieldStats[$field] ?? 0) + 1;
            }
        }
        arsort($missingFieldStats);
        
        $report = [
            'generatedAt' => date('c'),
            'summary' => [
                'totalInvoices' => $totalInvoices,
                'averageQualityScore' => $avgScore,
                'invoicesWithDummyData' => count($withDummy),
                'totalDummyFields' => $totalDummyFields,
                'averageDummyFieldsPerInvoice' => $totalInvoices > 0 ? round($totalDummyFields / $totalInvoices, 2) : 0,
                'mostCommonMissingFields' => $missingFieldStats
            ],
            'invoices' => []
        ];
        
        foreach ($results as $result) {
            $report['invoices'][] = [
                'file' => $result['file'],
                'invoiceNumber' => $result['invoiceNumber'],
                'qualityScore' => $result['score'],
                'sectionScores' => $result['sections'],
                'dummyFields' => $result['dummyFields'] ?? [],
                'missingFields' => $result['missingFields'] ?? [],
                'warnings' => $result['warnings'] ?? []
            ];
        }
        
        return $report;
    }
}
