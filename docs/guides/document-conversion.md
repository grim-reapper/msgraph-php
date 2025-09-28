# Document Conversion Guide

## Overview

This guide explains how to use the DocumentService for converting Microsoft Office documents to various formats, including the DOCX to PDF conversion feature.

## Supported Formats

### Input Formats
- **DOCX** - Microsoft Word documents
- **DOC** - Legacy Word documents
- **XLSX** - Excel spreadsheets
- **XLS** - Legacy Excel spreadsheets
- **PPTX** - PowerPoint presentations
- **PPT** - Legacy PowerPoint presentations
- **PDF** - PDF documents (for conversion to other formats)
- **RTF** - Rich Text Format
- **ODT** - OpenDocument Text
- **TXT** - Plain text files
- **HTML** - HTML documents

### Output Formats
- **PDF** - Portable Document Format
- **DOCX** - Microsoft Word format
- **HTML** - Web format
- **TXT** - Plain text
- **RTF** - Rich Text Format
- **ODT** - OpenDocument Text
- **Images** - PNG, JPG, GIF, TIFF, BMP

## Basic Usage

### Simple Conversion

```php
use GrimReapper\MsGraph\Services\DocumentService;

$documentService = new DocumentService($graphClient);

// Convert DOCX to PDF
try {
    $pdfContent = $documentService->convertDocxToPdf('/Documents/report.docx');

    // Save to local file
    file_put_contents('report.pdf', $pdfContent);
    echo "Conversion successful!";
} catch (Exception $e) {
    echo "Conversion failed: " . $e->getMessage();
}
```

### Generic Conversion Method

```php
// Convert any supported format to any other supported format
$convertedContent = $documentService->convertDocument(
    '/input/document.docx',  // Input file path
    'pdf'                    // Output format
);

// Convert with options
$convertedContent = $documentService->convertDocument(
    '/input/document.docx',
    'html',
    [
        'query' => [
            'inline_css' => 'true',
            'embed_images' => 'true'
        ]
    ]
);
```

### Check Conversion Capability

```php
// Check if conversion is supported
$canConvert = $documentService->canConvert('/document.docx', 'pdf');

if ($canConvert) {
    $pdfContent = $documentService->convertDocxToPdf('/document.docx');
} else {
    echo "Conversion not supported";
}
```

## Advanced Conversion Features

### Batch Conversion

Convert multiple documents in a single operation:

```php
$documents = [
    [
        'input_path' => '/Documents/report.docx',
        'output_path' => '/output/report.pdf',
    ],
    [
        'input_path' => '/Documents/manual.docx',
        'output_path' => '/output/manual.pdf',
    ],
    [
        'input_path' => '/Documents/guide.docx',
        'output_path' => '/output/guide.pdf',
    ],
];

$results = $documentService->batchConvert($documents, 'pdf');

echo "Converted: " . $results['summary']['successful'] . "\n";
echo "Failed: " . $results['summary']['failed'] . "\n";

// Process results
foreach ($results['results'] as $result) {
    if (isset($result['saved']) && $result['saved']) {
        echo "Saved: " . $result['output_path'] . "\n";
    }
}
```

### Conversion with Options

```php
// PDF conversion options
$pdfContent = $documentService->convertWithOptions(
    '/document.docx',
    'pdf',
    [
        'quality' => 'high',
        'orientation' => 'portrait',
        'page_range' => '1-5,8,11-13',
        'include_comments' => 'true',
        'include_track_changes' => 'true'
    ]
);

// HTML conversion options
$htmlContent = $documentService->convertWithOptions(
    '/document.docx',
    'html',
    [
        'inline_css' => 'true',
        'embed_images' => 'true',
        'include_headers_footers' => 'true',
        'encoding' => 'utf-8'
    ]
);
```

### Convert and Save Directly

```php
// Convert and save in one operation
$success = $documentService->convertAndSave(
    '/input/document.docx',    // Source file
    '/local/output.pdf',       // Local destination
    'pdf'                      // Output format
);

if ($success) {
    echo "Document converted and saved successfully";
} else {
    echo "Conversion and save failed";
}
```

### Progress Tracking

```php
// Track conversion progress (for large documents)
$convertedContent = $documentService->convertWithProgress(
    '/large-document.docx',
    'pdf',
    function ($percent, $message) {
        echo "Progress: {$percent}% - {$message}\n";
    }
);
```

## Document Information and Metadata

### Get Document Information

```php
// Get document metadata before conversion
$docInfo = $documentService->getDocumentInfo('/document.docx');
$metadata = $docInfo->getBody();

echo "Document Name: " . ($metadata['name'] ?? 'N/A') . "\n";
echo "Size: " . ($metadata['size'] ?? 'N/A') . " bytes\n";
echo "Created: " . ($metadata['createdDateTime'] ?? 'N/A') . "\n";
echo "Modified: " . ($metadata['lastModifiedDateTime'] ?? 'N/A') . "\n";
echo "MIME Type: " . ($metadata['file']['mimeType'] ?? 'N/A') . "\n";
```

### Get Supported Formats

```php
$supportedFormats = $documentService->getSupportedFormats();
echo "Supported formats: " . implode(', ', $supportedFormats) . "\n";

$inputFormats = $documentService->getSupportedInputFormats();
echo "Supported input formats: " . count($inputFormats) . "\n";
```

### Get Conversion Options

```php
$options = $documentService->getConversionOptions('pdf');
foreach ($options as $option => $description) {
    echo "{$option}: {$description}\n";
}
```

## Error Handling

### Comprehensive Error Handling

```php
try {
    $pdfContent = $documentService->convertDocxToPdf('/document.docx');
} catch (ServiceException $e) {
    switch ($e->getErrorCode()) {
        case ServiceException::ITEM_NOT_FOUND:
            echo "Document not found";
            break;
        case ServiceException::INVALID_REQUEST:
            echo "Invalid conversion request";
            break;
        case ServiceException::UNSUPPORTED_FORMAT:
            echo "Format not supported";
            break;
        default:
            echo "Conversion failed: " . $e->getMessage();
    }
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage();
}
```

### Validate Before Conversion

```php
function safeConvert(DocumentService $service, string $inputPath, string $outputFormat): ?string
{
    try {
        // Check if file exists
        $docInfo = $service->getDocumentInfo($inputPath);

        // Check if conversion is supported
        if (!$service->canConvert($inputPath, $outputFormat)) {
            throw new Exception("Conversion not supported");
        }

        // Check file size (example: max 50MB)
        $fileSize = $docInfo->get('size', 0);
        if ($fileSize > 50 * 1024 * 1024) {
            throw new Exception("File too large");
        }

        // Perform conversion
        return $service->convertDocument($inputPath, $outputFormat);

    } catch (Exception $e) {
        error_log("Conversion failed: " . $e->getMessage());
        return null;
    }
}
```

## Real-World Examples

### 1. Office Document Processing Pipeline

```php
class DocumentProcessor
{
    private DocumentService $documentService;

    public function processOfficeDocuments(array $files): array
    {
        $results = [];

        foreach ($files as $file) {
            $inputPath = $file['path'];
            $outputDir = $file['output_dir'];

            try {
                // Convert to PDF
                $pdfContent = $this->documentService->convertDocxToPdf($inputPath);
                $pdfPath = $outputDir . '/' . basename($inputPath) . '.pdf';
                file_put_contents($pdfPath, $pdfContent);

                // Convert to HTML for web viewing
                $htmlContent = $this->documentService->convertToHtml($inputPath);
                $htmlPath = $outputDir . '/' . basename($inputPath) . '.html';
                file_put_contents($htmlPath, $htmlContent);

                // Extract text for search indexing
                $textContent = $this->documentService->convertToText($inputPath);
                $textPath = $outputDir . '/' . basename($inputPath) . '.txt';
                file_put_contents($textPath, $textContent);

                $results[] = [
                    'input' => $inputPath,
                    'outputs' => [$pdfPath, $htmlPath, $textPath],
                    'status' => 'success'
                ];

            } catch (Exception $e) {
                $results[] = [
                    'input' => $inputPath,
                    'error' => $e->getMessage(),
                    'status' => 'failed'
                ];
            }
        }

        return $results;
    }
}
```

### 2. Document Conversion Web Service

```php
class ConversionAPI
{
    private DocumentService $documentService;

    public function convertDocument(string $inputPath, string $outputFormat): array
    {
        try {
            // Validate request
            if (!$this->documentService->canConvert($inputPath, $outputFormat)) {
                return [
                    'success' => false,
                    'error' => 'Conversion not supported'
                ];
            }

            // Perform conversion
            $content = $this->documentService->convertDocument($inputPath, $outputFormat);

            return [
                'success' => true,
                'content' => base64_encode($content),
                'size' => strlen($content),
                'format' => $outputFormat
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

### 3. Batch Document Processing

```php
class BatchProcessor
{
    private DocumentService $documentService;

    public function processFolder(string $folderPath, string $outputFormat): array
    {
        // Get all documents in folder (this would require OneDriveService)
        $documents = $this->getDocumentsInFolder($folderPath);

        $batchDocuments = [];
        foreach ($documents as $doc) {
            $batchDocuments[] = [
                'input_path' => $doc['path'],
                'output_path' => '/output/' . basename($doc['path']) . '.' . $outputFormat,
            ];
        }

        // Perform batch conversion
        return $this->documentService->batchConvert($batchDocuments, $outputFormat);
    }

    private function getDocumentsInFolder(string $folderPath): array
    {
        // Implementation would use OneDriveService to list files
        // This is a simplified example
        return [
            ['path' => $folderPath . '/doc1.docx'],
            ['path' => $folderPath . '/doc2.docx'],
        ];
    }
}
```

## Performance Optimization

### Caching Conversions

```php
class CachedDocumentService
{
    use HasCache;

    public function convertWithCache(string $inputPath, string $outputFormat): string
    {
        $cacheKey = 'conversion:' . md5($inputPath . $outputFormat);

        return $this->remember($cacheKey, function () use ($inputPath, $outputFormat) {
            return $this->documentService->convertDocument($inputPath, $outputFormat);
        }, 86400); // Cache for 24 hours
    }
}
```

### Parallel Processing

```php
class ParallelProcessor
{
    public function convertMultipleAsync(array $documents, string $outputFormat): array
    {
        $promises = [];
        $results = [];

        foreach ($documents as $index => $document) {
            $promises[$index] = async(function () use ($document, $outputFormat) {
                return $this->documentService->convertDocument(
                    $document['input_path'],
                    $outputFormat
                );
            });
        }

        // Wait for all conversions to complete
        foreach ($promises as $index => $promise) {
            try {
                $results[$index] = [
                    'content' => $promise->await(),
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $results[$index] = [
                    'error' => $e->getMessage(),
                    'status' => 'failed'
                ];
            }
        }

        return $results;
    }
}
```

## Integration Examples

### Laravel Integration

```php
// app/Services/MicrosoftGraphService.php
namespace App\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Services\DocumentService;

class MicrosoftGraphService
{
    private GraphClient $graphClient;
    private DocumentService $documentService;

    public function __construct()
    {
        $config = new AuthConfig([
            'clientId' => config('services.microsoft.client_id'),
            'clientSecret' => config('services.microsoft.client_secret'),
            'tenantId' => config('services.microsoft.tenant_id'),
            'redirectUri' => config('services.microsoft.redirect_uri'),
        ]);

        $this->graphClient = new GraphClient($config);
        $this->documentService = new DocumentService($this->graphClient);
    }

    public function convertToPdf(string $documentPath): string
    {
        return $this->documentService->convertDocxToPdf($documentPath);
    }
}
```

### Symfony Integration

```php
// src/Service/MicrosoftGraphService.php
namespace App\Service;

use GrimReapper\MsGraph\Services\DocumentService;

class MicrosoftGraphService
{
    private DocumentService $documentService;

    public function __construct(GraphClient $graphClient)
    {
        $this->documentService = new DocumentService($graphClient);
    }

    public function convertDocument(string $inputPath, string $outputFormat): string
    {
        return $this->documentService->convertDocument($inputPath, $outputFormat);
    }
}
```

## Troubleshooting

### Common Issues

1. **"Conversion not supported" error**
   ```php
   // Check if format is supported
   $supportedFormats = $documentService->getSupportedFormats();
   if (!in_array($outputFormat, $supportedFormats)) {
       throw new Exception("Format {$outputFormat} not supported");
   }
   ```

2. **"File not found" error**
   ```php
   // Verify file exists
   try {
       $docInfo = $documentService->getDocumentInfo($inputPath);
   } catch (ServiceException $e) {
       if ($e->isItemNotFound()) {
           throw new Exception("File not found: {$inputPath}");
       }
   }
   ```

3. **"Authentication failed" error**
   ```php
   // Check token validity
   try {
       $tokenManager->getAccessToken();
   } catch (AuthenticationException $e) {
       // Handle token refresh or re-authentication
   }
   ```

4. **"Rate limit exceeded" error**
   ```php
   // Implement retry with exponential backoff
   $retryCount = 0;
   $maxRetries = 3;

   do {
       try {
           return $documentService->convertDocument($inputPath, $outputFormat);
       } catch (ServiceException $e) {
           if ($e->isRateLimitExceeded() && $retryCount < $maxRetries) {
               sleep(pow(2, $retryCount));
               $retryCount++;
           } else {
               throw $e;
           }
       }
   } while ($retryCount <= $maxRetries);
   ```

### Debug Mode

Enable debug logging for troubleshooting:

```php
$logger = new Logger('document-conversion');
$logger->pushHandler(new StreamHandler('logs/conversion.log', Logger::DEBUG));

$documentService = new DocumentService($graphClient, $logger);
```

### Performance Monitoring

```php
class ConversionMonitor
{
    public function monitorConversion(callable $conversion, string $inputPath, string $outputFormat)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $result = $conversion();
            $success = true;
        } catch (Exception $e) {
            $success = false;
            throw $e;
        } finally {
            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;

            Log::info('Document conversion metrics', [
                'input_path' => $inputPath,
                'output_format' => $outputFormat,
                'duration' => $duration,
                'memory_used' => $memoryUsed,
                'success' => $success,
            ]);
        }

        return $result;
    }
}
```

## Best Practices

### 1. Validate Before Conversion
```php
function safeConversion(DocumentService $service, string $inputPath, string $outputFormat): ?string
{
    // Check if conversion is supported
    if (!$service->canConvert($inputPath, $outputFormat)) {
        return null;
    }

    // Check file size
    $docInfo = $service->getDocumentInfo($inputPath);
    $fileSize = $docInfo->get('size', 0);

    if ($fileSize > 100 * 1024 * 1024) { // 100MB limit
        throw new Exception('File too large');
    }

    return $service->convertDocument($inputPath, $outputFormat);
}
```

### 2. Use Appropriate Output Formats
```php
// For web viewing
$webContent = $documentService->convertToHtml($inputPath, [
    'inline_css' => 'true',
    'embed_images' => 'true'
]);

// For archiving
$archiveContent = $documentService->convertDocxToPdf($inputPath);

// For search indexing
$searchContent = $documentService->convertToText($inputPath);
```

### 3. Handle Large Files
```php
function handleLargeFile(DocumentService $service, string $inputPath, string $outputFormat): ?string
{
    $docInfo = $service->getDocumentInfo($inputPath);
    $fileSize = $docInfo->get('size', 0);

    if ($fileSize > 10 * 1024 * 1024) { // 10MB+
        // Use progress tracking for large files
        return $service->convertWithProgress(
            $inputPath,
            $outputFormat,
            function ($percent, $message) {
                echo "Converting: {$percent}% - {$message}\n";
            }
        );
    } else {
        return $service->convertDocument($inputPath, $outputFormat);
    }
}
```

### 4. Implement Caching
```php
class CachedConversionService
{
    use HasCache;

    public function convertCached(string $inputPath, string $outputFormat): string
    {
        $cacheKey = 'doc_conv:' . md5($inputPath . $outputFormat . filemtime($inputPath));

        return $this->remember($cacheKey, function () use ($inputPath, $outputFormat) {
            return $this->documentService->convertDocument($inputPath, $outputFormat);
        }, 3600); // Cache for 1 hour
    }
}
```

## Security Considerations

### File Access Control
```php
class SecureDocumentService
{
    public function convertUserDocument(string $userId, string $filePath, string $outputFormat): ?string
    {
        // Verify user has access to file
        if (!$this->userHasAccess($userId, $filePath)) {
            throw new Exception('Access denied');
        }

        // Log conversion activity
        $this->logConversion($userId, $filePath, $outputFormat);

        return $this->documentService->convertDocument($filePath, $outputFormat);
    }
}
```

### Content Validation
```php
function validateConvertedContent(string $content, string $expectedFormat): bool
{
    // Basic validation based on format
    switch ($expectedFormat) {
        case 'pdf':
            return str_starts_with($content, '%PDF-');
        case 'html':
            return str_contains($content, '<html') || str_contains($content, '<HTML');
        case 'txt':
            return !str_contains($content, '<html') && !str_contains($content, '%PDF-');
        default:
            return true;
    }
}
```

## API Reference

### DocumentService Methods

| Method | Description | Parameters |
|--------|-------------|------------|
| `convertDocument($inputPath, $outputFormat, $options)` | Convert document to specified format | string, string, array |
| `convertDocxToPdf($inputPath, $options)` | Convert DOCX to PDF | string, array |
| `convertToHtml($inputPath, $options)` | Convert to HTML | string, array |
| `convertToText($inputPath, $options)` | Convert to plain text | string, array |
| `batchConvert($documents, $outputFormat, $options)` | Batch convert multiple documents | array, string, array |
| `canConvert($inputPath, $outputFormat)` | Check if conversion is supported | string, string |
| `getDocumentInfo($path)` | Get document metadata | string |
| `getSupportedFormats()` | Get list of supported output formats | void |
| `getConversionOptions($format)` | Get conversion options for format | string |

### Conversion Options by Format

#### PDF Options
- `quality`: 'standard', 'high'
- `orientation`: 'portrait', 'landscape'
- `page_range`: '1-5,8,11-13'
- `include_comments`: boolean
- `include_track_changes`: boolean

#### HTML Options
- `inline_css`: boolean
- `embed_images`: boolean
- `include_headers_footers`: boolean
- `encoding`: 'utf-8', 'utf-16', 'iso-8859-1'

#### Text Options
- `encoding`: 'utf-8', 'utf-16', 'iso-8859-1'
- `line_ending`: 'crlf', 'lf'
- `include_formatting`: boolean

## Examples and Testing

### Create Test Documents

```php
function createTestDocument(string $path, string $content): void
{
    // Create a simple DOCX file for testing
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText($content);

    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($path);
}
```

### Test Conversion Pipeline

```php
class ConversionTest
{
    public function testConversionPipeline(): void
    {
        $testDocx = '/test/test-document.docx';
        $this->createTestDocument($testDocx, 'Test content for conversion');

        // Test PDF conversion
        $pdfContent = $this->documentService->convertDocxToPdf($testDocx);
        $this->assertStringStartsWith('%PDF-', $pdfContent);

        // Test HTML conversion
        $htmlContent = $this->documentService->convertToHtml($testDocx);
        $this->assertStringContains('<html', strtolower($htmlContent));

        // Test text conversion
        $textContent = $this->documentService->convertToText($testDocx);
        $this->assertStringContains('Test content', $textContent);

        // Cleanup
        $this->oneDrive->deleteItem($testDocx);
    }
}
```

## Support and Resources

### Getting Help

1. **API Documentation**: `/docs/api/index.md`
2. **Examples**: `/docs/examples/document-conversion.php`
3. **Best Practices**: `/docs/guides/best-practices.md`
4. **GitHub Issues**: [Report bugs and request features](https://github.com/grim-reapper/msgraph-php/issues)
5. **Discussions**: [Community discussions](https://github.com/grim-reapper/msgraph-php/discussions)

### Related Documentation

- [Authentication Guide](authentication.md)
- [OneDrive Operations Examples](../examples/onedrive-operations.php)
- [API Reference](../../docs/api/index.md)
- [Best Practices Guide](best-practices.md)

### External Resources

- [Microsoft Graph API Documentation](https://docs.microsoft.com/en-us/graph/api/resources/onedrive)
- [Office Conversion API Reference](https://docs.microsoft.com/en-us/onedrive/developer/rest-api/api/driveitem_get_content_format)
- [Supported File Formats](https://docs.microsoft.com/en-us/onedrive/developer/rest-api/concepts/conversion-limits)

## Next Steps

1. Set up authentication following the [Authentication Guide](authentication.md)
2. Test basic conversions with the provided examples
3. Implement error handling and logging
4. Set up caching for better performance
5. Integrate with your application's workflow
6. Monitor performance and usage metrics
