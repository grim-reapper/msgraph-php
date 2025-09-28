<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Services\DocumentService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Document Conversion Examples.
 *
 * This file demonstrates document conversion operations using the Microsoft Graph PHP Package.
 */

// Setup logging
$logger = new Logger('msgraph-docs');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Initialize configuration
$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback',
    'scopes' => [
        'https://graph.microsoft.com/User.Read',
        'https://graph.microsoft.com/Files.ReadWrite.All',
    ],
    'accessToken' => 'your-access-token',
]);

// Initialize cache
$cache = new FilesystemAdapter('msgraph', 3600, __DIR__ . '/../../cache');

// Initialize Graph client
$graphClient = new GraphClient($config, null, $logger, $cache);

// Initialize Document service
$documentService = new DocumentService($graphClient, $logger);

echo "=== Document Conversion Examples ===\n\n";

try {
    // Example 1: Check supported formats
    echo "1. Supported Formats:\n";
    echo "--------------------\n";

    $supportedFormats = $documentService->getSupportedFormats();
    echo "Supported output formats:\n";
    foreach ($supportedFormats as $format) {
        echo "  - {$format}\n";
    }
    echo "\n";

    // Example 2: Get conversion options for specific format
    echo "2. PDF Conversion Options:\n";
    echo "-------------------------\n";

    $pdfOptions = $documentService->getConversionOptions('pdf');
    echo "PDF conversion options:\n";
    foreach ($pdfOptions as $option => $description) {
        echo "  - {$option}: {$description}\n";
    }
    echo "\n";

    // Example 3: Check conversion capability
    echo "3. Conversion Capability Check:\n";
    echo "------------------------------\n";

    $testFiles = [
        '/document.docx',
        '/spreadsheet.xlsx',
        '/presentation.pptx',
        '/text-file.txt',
    ];

    foreach ($testFiles as $file) {
        $canConvertToPdf = $documentService->canConvert($file, 'pdf');
        $fileName = basename($file);
        echo "Can convert {$fileName} to PDF: " . ($canConvertToPdf ? 'Yes' : 'No') . "\n";
    }
    echo "\n";

    // Example 4: Convert DOCX to PDF
    echo "4. DOCX to PDF Conversion:\n";
    echo "--------------------------\n";

    $docxFile = '/sample-document.docx';

    try {
        // Check if file exists and can be converted
        if ($documentService->canConvert($docxFile, 'pdf')) {
            $pdfContent = $documentService->convertDocxToPdf($docxFile);

            echo "DOCX to PDF conversion successful!\n";
            echo 'PDF size: ' . strlen($pdfContent) . " bytes\n";

            // Save PDF to local file
            $localPdfPath = __DIR__ . '/converted-document.pdf';
            if (file_put_contents($localPdfPath, $pdfContent)) {
                echo "PDF saved to: {$localPdfPath}\n";
            }
        } else {
            echo "Cannot convert {$docxFile} to PDF\n";
        }
    } catch (Exception $e) {
        echo 'DOCX to PDF conversion failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 5: Convert to HTML
    echo "5. Document to HTML Conversion:\n";
    echo "------------------------------\n";

    $htmlFile = '/sample-document.docx';

    try {
        if ($documentService->canConvert($htmlFile, 'html')) {
            $htmlContent = $documentService->convertToHtml($htmlFile);

            echo "Document to HTML conversion successful!\n";
            echo 'HTML size: ' . strlen($htmlContent) . " bytes\n";
            echo "HTML preview (first 200 chars):\n";
            echo substr($htmlContent, 0, 200) . "...\n";

            // Save HTML to local file
            $localHtmlPath = __DIR__ . '/converted-document.html';
            if (file_put_contents($localHtmlPath, $htmlContent)) {
                echo "HTML saved to: {$localHtmlPath}\n";
            }
        }
    } catch (Exception $e) {
        echo 'Document to HTML conversion failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 6: Convert to plain text
    echo "6. Document to Text Conversion:\n";
    echo "------------------------------\n";

    $textFile = '/sample-document.docx';

    try {
        if ($documentService->canConvert($textFile, 'txt')) {
            $textContent = $documentService->convertToText($textFile);

            echo "Document to text conversion successful!\n";
            echo 'Text size: ' . strlen($textContent) . " bytes\n";
            echo "Text preview (first 200 chars):\n";
            echo substr($textContent, 0, 200) . "...\n";

            // Save text to local file
            $localTextPath = __DIR__ . '/converted-document.txt';
            if (file_put_contents($localTextPath, $textContent)) {
                echo "Text saved to: {$localTextPath}\n";
            }
        }
    } catch (Exception $e) {
        echo 'Document to text conversion failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 7: Batch conversion
    echo "7. Batch Conversion:\n";
    echo "-------------------\n";

    $documentsToConvert = [
        [
            'input_path' => '/document1.docx',
            'output_path' => __DIR__ . '/output1.pdf',
        ],
        [
            'input_path' => '/document2.docx',
            'output_path' => __DIR__ . '/output2.pdf',
        ],
        [
            'input_path' => '/document3.docx',
            'output_path' => __DIR__ . '/output3.pdf',
        ],
    ];

    try {
        $batchResults = $documentService->batchConvert($documentsToConvert, 'pdf');

        echo "Batch conversion completed:\n";
        echo 'Total documents: ' . $batchResults['summary']['total'] . "\n";
        echo 'Successful: ' . $batchResults['summary']['successful'] . "\n";
        echo 'Failed: ' . $batchResults['summary']['failed'] . "\n";

        foreach ($batchResults['results'] as $result) {
            $inputPath = $result['input_path'];
            $size = $result['size'];
            $status = isset($result['saved']) && $result['saved'] ? 'Saved' : 'Not saved';
            echo "  - {$inputPath}: {$size} bytes ({$status})\n";
        }

        if (!empty($batchResults['errors'])) {
            echo "Errors:\n";
            foreach ($batchResults['errors'] as $error) {
                echo "  - {$error['input_path']}: {$error['error']}\n";
            }
        }
    } catch (Exception $e) {
        echo 'Batch conversion failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 8: Convert with specific options
    echo "8. Conversion with Options:\n";
    echo "--------------------------\n";

    try {
        $pdfWithOptions = $documentService->convertWithOptions(
            '/document.docx',
            'pdf',
            [
                'quality' => 'high',
                'orientation' => 'portrait',
            ]
        );

        echo "PDF with options conversion successful!\n";
        echo 'PDF size: ' . strlen($pdfWithOptions) . " bytes\n";

        // Save with options
        $optionsPdfPath = __DIR__ . '/document-with-options.pdf';
        if (file_put_contents($optionsPdfPath, $pdfWithOptions)) {
            echo "PDF with options saved to: {$optionsPdfPath}\n";
        }
    } catch (Exception $e) {
        echo 'Conversion with options failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 9: Convert and save directly
    echo "9. Convert and Save:\n";
    echo "-------------------\n";

    try {
        $directSave = $documentService->convertAndSave(
            '/document.docx',
            __DIR__ . '/direct-save.pdf',
            'pdf'
        );

        if ($directSave) {
            echo "Document converted and saved successfully\n";
        } else {
            echo "Failed to convert and save document\n";
        }
    } catch (Exception $e) {
        echo 'Convert and save failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 10: Get document information before conversion
    echo "10. Document Information:\n";
    echo "------------------------\n";

    $infoFile = '/sample-document.docx';

    try {
        $docInfo = $documentService->getDocumentInfo($infoFile);
        $metadata = $docInfo->getBody();

        echo "Document information:\n";
        echo '  Name: ' . ($metadata['name'] ?? 'N/A') . "\n";
        echo '  Size: ' . ($metadata['size'] ?? 'N/A') . " bytes\n";
        echo '  Created: ' . ($metadata['createdDateTime'] ?? 'N/A') . "\n";
        echo '  Modified: ' . ($metadata['lastModifiedDateTime'] ?? 'N/A') . "\n";
        echo '  MIME Type: ' . ($metadata['file']['mimeType'] ?? 'N/A') . "\n";
    } catch (Exception $e) {
        echo 'Failed to get document info: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 11: Convert to image
    echo "11. Document to Image Conversion:\n";
    echo "--------------------------------\n";

    try {
        $imageContent = $documentService->convertToImage('/document.docx', 'png');

        echo "Document to image conversion successful!\n";
        echo 'Image size: ' . strlen($imageContent) . " bytes\n";

        // Save image
        $imagePath = __DIR__ . '/document-preview.png';
        if (file_put_contents($imagePath, $imageContent)) {
            echo "Image saved to: {$imagePath}\n";
        }
    } catch (Exception $e) {
        echo 'Document to image conversion failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 12: Progress tracking conversion
    echo "12. Conversion with Progress Tracking:\n";
    echo "-------------------------------------\n";

    try {
        $progressData = [];

        $convertedWithProgress = $documentService->convertWithProgress(
            '/large-document.docx',
            'pdf',
            function ($percent, $message) use (&$progressData): void {
                $progressData[] = ['percent' => $percent, 'message' => $message];
                echo "Progress: {$percent}% - {$message}\n";
            }
        );

        echo "Conversion with progress tracking completed!\n";
        echo 'Progress steps: ' . count($progressData) . "\n";
    } catch (Exception $e) {
        echo 'Progress tracking conversion failed: ' . $e->getMessage() . "\n";
    }
    echo "\n";

    // Example 13: Error handling demonstration
    echo "13. Error Handling:\n";
    echo "------------------\n";

    $invalidOperations = [
        ['path' => '/non-existent.docx', 'format' => 'pdf'],
        ['path' => '/document.docx', 'format' => 'invalid'],
        ['path' => '/document.xyz', 'format' => 'pdf'],
    ];

    foreach ($invalidOperations as $i => $operation) {
        try {
            $documentService->convertDocument($operation['path'], $operation['format']);
            echo "Operation {$i} succeeded unexpectedly\n";
        } catch (Exception $e) {
            echo "Operation {$i} failed as expected: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";

    // Example 14: Get conversion statistics
    echo "14. Conversion Statistics:\n";
    echo "-------------------------\n";

    $stats = $documentService->getConversionStats();

    echo "Conversion statistics:\n";
    echo '  Supported formats: ' . count($stats['supported_formats']) . "\n";
    echo '  Supported input formats: ' . count($stats['supported_input_formats']) . "\n";
    echo '  Total conversions: ' . ($stats['total_conversions'] ?? 0) . "\n";
    echo "\n";

    // Example 15: Multiple format conversion
    echo "15. Multiple Format Conversion:\n";
    echo "------------------------------\n";

    $sourceFile = '/sample-document.docx';
    $formats = ['pdf', 'html', 'txt'];

    foreach ($formats as $format) {
        try {
            if ($documentService->canConvert($sourceFile, $format)) {
                $content = $documentService->convertDocument($sourceFile, $format);
                $outputPath = __DIR__ . "/sample-document.{$format}";

                if (file_put_contents($outputPath, $content)) {
                    echo "Converted to {$format}: " . strlen($content) . " bytes\n";
                }
            } else {
                echo "Cannot convert to {$format}\n";
            }
        } catch (Exception $e) {
            echo "Failed to convert to {$format}: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";

    // Cleanup: Remove generated files
    echo "16. Cleanup:\n";
    echo "-----------\n";

    $filesToClean = [
        __DIR__ . '/converted-document.pdf',
        __DIR__ . '/converted-document.html',
        __DIR__ . '/converted-document.txt',
        __DIR__ . '/document-with-options.pdf',
        __DIR__ . '/direct-save.pdf',
        __DIR__ . '/document-preview.png',
        __DIR__ . '/sample-document.pdf',
        __DIR__ . '/sample-document.html',
        __DIR__ . '/sample-document.txt',
    ];

    $cleaned = 0;
    foreach ($filesToClean as $file) {
        if (file_exists($file)) {
            unlink($file);
            $cleaned++;
        }
    }

    echo "Cleaned up {$cleaned} files\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Document Conversion Examples Completed ===\n";

/**
 * Helper Functions.
 */

/**
 * Example: Convert Office documents to PDF.
 */
function convertOfficeToPdfExample(DocumentService $documentService): void
{
    echo "Converting Office documents to PDF...\n";

    $officeFiles = [
        'document.docx' => '/Documents/report.docx',
        'spreadsheet.xlsx' => '/Data/sales.xlsx',
        'presentation.pptx' => '/Presentations/meeting.pptx',
    ];

    foreach ($officeFiles as $name => $path) {
        try {
            if ($documentService->canConvert($path, 'pdf')) {
                $pdfContent = $documentService->convertDocument($path, 'pdf');
                $outputPath = __DIR__ . '/' . $name . '.pdf';

                if (file_put_contents($outputPath, $pdfContent)) {
                    echo "Converted {$name} to PDF: " . strlen($pdfContent) . " bytes\n";
                }
            }
        } catch (Exception $e) {
            echo "Failed to convert {$name}: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Example: Convert documents for web viewing.
 */
function convertForWebViewingExample(DocumentService $documentService): void
{
    echo "Converting documents for web viewing...\n";

    $documents = [
        '/Documents/manual.docx',
        '/Reports/annual-report.docx',
        '/Guides/user-guide.docx',
    ];

    foreach ($documents as $docPath) {
        try {
            // Convert to HTML for web viewing
            $htmlContent = $documentService->convertToHtml($docPath, [
                'inline_css' => true,
                'embed_images' => true,
            ]);

            $outputPath = __DIR__ . '/web-' . basename($docPath) . '.html';
            if (file_put_contents($outputPath, $htmlContent)) {
                echo 'Converted for web viewing: ' . basename($docPath) . "\n";
            }
        } catch (Exception $e) {
            echo 'Failed to convert for web viewing: ' . $e->getMessage() . "\n";
        }
    }
}

/**
 * Example: Extract text from documents.
 */
function extractTextExample(DocumentService $documentService): void
{
    echo "Extracting text from documents...\n";

    $documents = [
        '/Contracts/contract.docx',
        '/Notes/meeting-notes.docx',
        '/Articles/article.docx',
    ];

    foreach ($documents as $docPath) {
        try {
            $textContent = $documentService->convertToText($docPath);
            $outputPath = __DIR__ . '/text-' . basename($docPath) . '.txt';

            if (file_put_contents($outputPath, $textContent)) {
                echo 'Text extracted: ' . basename($docPath) . ' (' . strlen($textContent) . " chars)\n";
            }
        } catch (Exception $e) {
            echo 'Failed to extract text: ' . $e->getMessage() . "\n";
        }
    }
}

/**
 * Example: Create document thumbnails.
 */
function createThumbnailsExample(DocumentService $documentService): void
{
    echo "Creating document thumbnails...\n";

    $documents = [
        '/Documents/report.docx',
        '/Presentations/slides.pptx',
        '/PDFs/manual.pdf',
    ];

    foreach ($documents as $docPath) {
        try {
            $thumbnail = $documentService->getConversionPreview($docPath, 'png');
            $outputPath = __DIR__ . '/thumb-' . basename($docPath) . '.png';

            if (file_put_contents($outputPath, $thumbnail)) {
                echo 'Thumbnail created: ' . basename($docPath) . "\n";
            }
        } catch (Exception $e) {
            echo 'Failed to create thumbnail: ' . $e->getMessage() . "\n";
        }
    }
}

/**
 * Example: Batch convert entire folder.
 */
function batchConvertFolderExample(DocumentService $documentService, string $folderPath): void
{
    echo "Batch converting folder contents...\n";

    try {
        // This would require listing files in the folder first
        // For demonstration, we'll use a predefined list
        $files = [
            $folderPath . '/file1.docx',
            $folderPath . '/file2.docx',
            $folderPath . '/file3.docx',
        ];

        $documents = [];
        foreach ($files as $file) {
            $documents[] = [
                'input_path' => $file,
                'output_path' => __DIR__ . '/batch-' . basename($file) . '.pdf',
            ];
        }

        $results = $documentService->batchConvert($documents, 'pdf');

        echo "Folder batch conversion completed:\n";
        echo '  Successful: ' . $results['summary']['successful'] . "\n";
        echo '  Failed: ' . $results['summary']['failed'] . "\n";
    } catch (Exception $e) {
        echo 'Folder batch conversion failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Document format compatibility check.
 */
function formatCompatibilityExample(DocumentService $documentService): void
{
    echo "Checking document format compatibility...\n";

    $testCases = [
        ['input' => 'docx', 'output' => 'pdf'],
        ['input' => 'docx', 'output' => 'html'],
        ['input' => 'pdf', 'output' => 'docx'],
        ['input' => 'txt', 'output' => 'pdf'],
        ['input' => 'rtf', 'output' => 'pdf'],
    ];

    foreach ($testCases as $test) {
        $canConvert = $documentService->canConvert("/test.{$test['input']}", $test['output']);
        echo "{$test['input']} -> {$test['output']}: " . ($canConvert ? 'Supported' : 'Not supported') . "\n";
    }
}

/**
 * Example: Document conversion workflow.
 */
function documentWorkflowExample(DocumentService $documentService): void
{
    echo "Document conversion workflow...\n";

    try {
        // 1. Upload document
        echo "1. Document uploaded\n";

        // 2. Get document info
        $docInfo = $documentService->getDocumentInfo('/workflow-document.docx');
        echo "2. Document info retrieved\n";

        // 3. Convert to multiple formats
        $formats = ['pdf', 'html', 'txt'];
        foreach ($formats as $format) {
            if ($documentService->canConvert('/workflow-document.docx', $format)) {
                $content = $documentService->convertDocument('/workflow-document.docx', $format);
                echo "3. Converted to {$format}\n";
            }
        }

        // 4. Create archive with all formats
        echo "4. Archive created with all formats\n";

        echo "Workflow completed successfully!\n";
    } catch (Exception $e) {
        echo 'Workflow failed: ' . $e->getMessage() . "\n";
    }
}

// Additional examples (uncomment to run)
// convertOfficeToPdfExample($documentService);
// convertForWebViewingExample($documentService);
// extractTextExample($documentService);
// createThumbnailsExample($documentService);
// batchConvertFolderExample($documentService, '/Documents');
// formatCompatibilityExample($documentService);
// documentWorkflowExample($documentService);

echo "\n=== Additional Examples Available ===\n";
echo "Uncomment the function calls above to run more examples:\n";
echo "- convertOfficeToPdfExample()\n";
echo "- convertForWebViewingExample()\n";
echo "- extractTextExample()\n";
echo "- createThumbnailsExample()\n";
echo "- batchConvertFolderExample()\n";
echo "- formatCompatibilityExample()\n";
echo "- documentWorkflowExample()\n";

echo "\n=== Document Conversion Examples Documentation ===\n";
echo "For more information, see:\n";
echo "- API Documentation: docs/api/index.md\n";
echo "- Document Conversion Guide: docs/guides/document-conversion.md\n";
echo "- Best Practices: docs/guides/best-practices.md\n";
