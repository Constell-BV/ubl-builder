<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dave\UblBuilder\InvoiceExtractor;
use Dave\UblBuilder\InvoiceBuilder;
use Dave\UblBuilder\OpenApiClient;
use Dave\UblBuilder\DataQualityAnalyzer;
use Dotenv\Dotenv;
use Einvoicing\Writers\UblWriter;
use Einvoicing\Exceptions\ValidationException;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize clients
echo "Initializing OpenAI client...\n";
$openaiClient = OpenApiClient::fromEnvironment();
$extractor = new InvoiceExtractor($openaiClient, 'gpt-4o-mini');
$builder = new InvoiceBuilder();
$writer = new UblWriter();
$analyzer = new DataQualityAnalyzer();

// Define directories
$examplesDir = __DIR__ . '/examples';
$outputDir = __DIR__ . '/output';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
    echo "Created output directory: $outputDir\n";
}

// Get all HTML files from examples directory
$htmlFiles = glob($examplesDir . '/*.html');

if (empty($htmlFiles)) {
    echo "No HTML files found in $examplesDir\n";
    exit(1);
}

echo "Found " . count($htmlFiles) . " HTML file(s) to process\n\n";

// Track statistics and results
$stats = [
    'total' => count($htmlFiles),
    'success' => 0,
    'failed' => 0,
    'validationErrors' => 0,
    'errors' => []
];

$results = [];

// Process each HTML file
foreach ($htmlFiles as $htmlFile) {
    $basename = basename($htmlFile, '.html');
    echo "Processing: $basename.html\n";
    
    try {
        // Read HTML content
        $htmlContent = file_get_contents($htmlFile);
        if ($htmlContent === false) {
            throw new Exception("Failed to read file: $htmlFile");
        }
        
        // Extract invoice data
        $invoiceData = $extractor->extractFromHtml($htmlContent);
        
        // Calculate completeness score
        $completeness = $analyzer->calculateCompletenessScore($invoiceData);
        
        // Display detailed report
        echo $analyzer->generateConsoleReport($basename . '.html', $invoiceData, $completeness);
        
        // Store results for summary
        $results[] = [
            'file' => $basename,
            'invoiceNumber' => $invoiceData['invoice']['number'],
            'score' => $completeness['overall'],
            'sections' => $completeness['sections'],
            'dummyFields' => $completeness['dummyFields'],
            'missingFields' => $completeness['missingFields'],
            'warnings' => $completeness['warnings']
        ];
        
        // Build invoice object
        $invoice = $builder->build($invoiceData);
        
        // Validate invoice
        try {
            $invoice->validate();
            echo "✅ UBL Validation: PASSED\n";
        } catch (ValidationException $e) {
            echo "❌ UBL Validation: FAILED - " . $e->getMessage() . "\n";
            $stats['validationErrors']++;
        }
        
        // Generate and save XML
        $xmlPath = $outputDir . '/' . $basename . '.xml';
        $xmlContent = $writer->export($invoice);
        file_put_contents($xmlPath, $xmlContent);
        
        // Generate and save JSON
        $jsonPath = $outputDir . '/' . $basename . '.json';
        $jsonContent = json_encode($invoiceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($jsonPath, $jsonContent);
        
        echo "\n💾 Files saved: $basename.xml, $basename.json\n";
        
        $stats['success']++;
        
    } catch (Exception $e) {
        $stats['failed']++;
        $stats['errors'][] = [
            'file' => $basename,
            'error' => $e->getMessage()
        ];
        echo "  ✗ FAILED: " . $e->getMessage() . "\n\n";
    }
}

// Generate and display summary
echo $analyzer->generateSummaryReport($results);

// Save detailed report to JSON
$reportPath = $outputDir . '/extraction-report.json';
$detailedReport = $analyzer->generateDetailedReport($results);
file_put_contents($reportPath, json_encode($detailedReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n📊 Detailed report saved to: $reportPath\n";

if (!empty($stats['errors'])) {
    echo "\n❌ ERRORS:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - {$error['file']}: {$error['error']}\n";
    }
}

echo "\n✅ Generation complete!\n";

// Exit with appropriate status code
exit($stats['failed'] > 0 ? 1 : 0);
