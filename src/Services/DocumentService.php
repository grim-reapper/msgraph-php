<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Traits\HasLogging;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for Microsoft document conversion operations.
 */
final class DocumentService
{
    use HasLogging;

    private GraphClient $graphClient;
    private array $supportedFormats = [
        'pdf',
        'docx',
        'doc',
        'html',
        'rtf',
        'odt',
        'txt',
        'jpg',
        'png',
        'gif',
        'tiff',
        'bmp',
    ];

    public function __construct(GraphClient $graphClient, ?LoggerInterface $logger = null)
    {
        $this->graphClient = $graphClient;

        if ($logger !== null) {
            $this->setLogger($logger);
        } else {
            $this->setLogger(new NullLogger());
        }
    }

    /**
     * Convert a document to the specified format.
     */
    public function convertDocument(string $inputPath, string $outputFormat, array $options = []): string
    {
        $this->validateFormat($outputFormat);

        $endpoint = "/me/drive/root:{$inputPath}:/content?format={$outputFormat}";

        $this->logInfo('Converting document', [
            'input_path' => $inputPath,
            'output_format' => $outputFormat,
            'options' => array_keys($options),
        ]);

        try {
            $response = $this->graphClient->api('GET', $endpoint, $options);

            $this->logInfo('Document converted successfully', [
                'input_path' => $inputPath,
                'output_format' => $outputFormat,
                'size' => strlen($response->getBody()),
            ]);

            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to convert document: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Convert DOCX to PDF.
     */
    public function convertDocxToPdf(string $inputPath, array $options = []): string
    {
        return $this->convertDocument($inputPath, 'pdf', $options);
    }

    /**
     * Convert document to HTML.
     */
    public function convertToHtml(string $inputPath, array $options = []): string
    {
        return $this->convertDocument($inputPath, 'html', $options);
    }

    /**
     * Convert document to plain text.
     */
    public function convertToText(string $inputPath, array $options = []): string
    {
        return $this->convertDocument($inputPath, 'txt', $options);
    }

    /**
     * Convert document to RTF.
     */
    public function convertToRtf(string $inputPath, array $options = []): string
    {
        return $this->convertDocument($inputPath, 'rtf', $options);
    }

    /**
     * Convert document to DOCX.
     */
    public function convertToDocx(string $inputPath, array $options = []): string
    {
        return $this->convertDocument($inputPath, 'docx', $options);
    }

    /**
     * Convert document to image (first page).
     */
    public function convertToImage(string $inputPath, string $imageFormat = 'png', array $options = []): string
    {
        $this->validateImageFormat($imageFormat);
        return $this->convertDocument($inputPath, $imageFormat, $options);
    }

    /**
     * Batch convert multiple documents.
     */
    public function batchConvert(array $documents, string $outputFormat, array $options = []): array
    {
        $results = [];
        $errors = [];

        $this->logInfo('Starting batch conversion', [
            'document_count' => count($documents),
            'output_format' => $outputFormat,
        ]);

        foreach ($documents as $index => $document) {
            try {
                $inputPath = $document['input_path'] ?? $document['path'] ?? '';
                $outputPath = $document['output_path'] ?? null;

                if (empty($inputPath)) {
                    throw new ServiceException('Input path is required for document conversion');
                }

                $convertedContent = $this->convertDocument($inputPath, $outputFormat, $options);

                $result = [
                    'input_path' => $inputPath,
                    'output_format' => $outputFormat,
                    'content' => $convertedContent,
                    'size' => strlen($convertedContent),
                ];

                if ($outputPath) {
                    $result['output_path'] = $outputPath;
                    // Save to output path if provided
                    if (file_put_contents($outputPath, $convertedContent) !== false) {
                        $result['saved'] = true;
                    } else {
                        $result['saved'] = false;
                        $result['error'] = 'Failed to save file';
                    }
                }

                $results[] = $result;

                $this->logDebug('Document converted in batch', [
                    'index' => $index,
                    'input_path' => $inputPath,
                    'size' => strlen($convertedContent),
                ]);
            } catch (ServiceException $e) {
                $errors[] = [
                    'index' => $index,
                    'input_path' => $document['input_path'] ?? $document['path'] ?? '',
                    'error' => $e->getMessage(),
                ];

                $this->logWarning('Document conversion failed in batch', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logInfo('Batch conversion completed', [
            'total' => count($documents),
            'successful' => count($results),
            'failed' => count($errors),
        ]);

        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($documents),
                'successful' => count($results),
                'failed' => count($errors),
            ],
        ];
    }

    /**
     * Get supported conversion formats.
     */
    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    /**
     * Check if a format is supported.
     */
    public function isFormatSupported(string $format): bool
    {
        return in_array(strtolower($format), $this->supportedFormats, true);
    }

    /**
     * Get conversion options for a specific format.
     */
    public function getConversionOptions(string $format): array
    {
        $this->validateFormat($format);

        return match (strtolower($format)) {
            'pdf' => [
                'quality' => ['standard', 'high'],
                'orientation' => ['portrait', 'landscape'],
                'page_range' => 'string (e.g., "1-5,8,11-13")',
                'include_comments' => 'boolean',
                'include_track_changes' => 'boolean',
            ],
            'html' => [
                'inline_css' => 'boolean',
                'embed_images' => 'boolean',
                'include_headers_footers' => 'boolean',
                'encoding' => ['utf-8', 'utf-16', 'iso-8859-1'],
            ],
            'txt' => [
                'encoding' => ['utf-8', 'utf-16', 'iso-8859-1'],
                'line_ending' => ['crlf', 'lf'],
                'include_formatting' => 'boolean',
            ],
            'docx' => [
                'compatibility_mode' => 'string (e.g., "Word14", "Word15")',
                'maintain_structure' => 'boolean',
            ],
            'rtf' => [
                'include_images' => 'boolean',
                'include_headers_footers' => 'boolean',
            ],
            default => [
                'quality' => 'string (low, medium, high)',
            ],
        };
    }

    /**
     * Convert with specific options.
     */
    public function convertWithOptions(
        string $inputPath,
        string $outputFormat,
        array $conversionOptions = []
    ): string {
        $options = [];

        if (!empty($conversionOptions)) {
            // Add format-specific query parameters
            foreach ($conversionOptions as $key => $value) {
                $options['query'][$key] = $value;
            }
        }

        return $this->convertDocument($inputPath, $outputFormat, $options);
    }

    /**
     * Get document metadata before conversion.
     */
    public function getDocumentInfo(string $path): GraphResponse
    {
        $endpoint = "/me/drive/root:{$path}";

        $this->logInfo('Getting document info', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get document info: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Check if a document can be converted to the specified format.
     */
    public function canConvert(string $inputPath, string $outputFormat): bool
    {
        try {
            $docInfo = $this->getDocumentInfo($inputPath);
            $fileName = $docInfo->get('name', '');

            if (empty($fileName)) {
                return false;
            }

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $supportedInputFormats = $this->getSupportedInputFormats();

            return isset($supportedInputFormats[$extension]) &&
                   in_array($outputFormat, $supportedInputFormats[$extension], true);
        } catch (ServiceException $e) {
            $this->logWarning('Cannot determine if document can be converted', [
                'path' => $inputPath,
                'format' => $outputFormat,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get supported input formats and their possible output formats.
     */
    public function getSupportedInputFormats(): array
    {
        return [
            'docx' => ['pdf', 'html', 'txt', 'rtf', 'odt', 'doc'],
            'doc' => ['pdf', 'html', 'txt', 'rtf', 'odt', 'docx'],
            'pdf' => ['docx', 'html', 'txt', 'jpg', 'png'],
            'html' => ['pdf', 'docx', 'txt'],
            'rtf' => ['pdf', 'docx', 'html', 'txt'],
            'odt' => ['pdf', 'docx', 'html', 'txt'],
            'txt' => ['pdf', 'docx', 'html'],
            'xls' => ['pdf', 'html', 'txt'],
            'xlsx' => ['pdf', 'html', 'txt'],
            'ppt' => ['pdf'],
            'pptx' => ['pdf'],
        ];
    }

    /**
     * Convert and save to local file.
     */
    public function convertAndSave(
        string $inputPath,
        string $outputPath,
        string $outputFormat,
        array $options = []
    ): bool {
        try {
            $convertedContent = $this->convertDocument($inputPath, $outputFormat, $options);

            $result = file_put_contents($outputPath, $convertedContent);

            if ($result !== false) {
                $this->logInfo('Document converted and saved', [
                    'input_path' => $inputPath,
                    'output_path' => $outputPath,
                    'format' => $outputFormat,
                    'size' => strlen($convertedContent),
                ]);
                return true;
            }
            $this->logError('Failed to save converted document', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
            ]);
            return false;
        } catch (ServiceException $e) {
            $this->logError('Document conversion and save failed', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get conversion preview (thumbnail).
     */
    public function getConversionPreview(string $inputPath, string $outputFormat = 'png'): string
    {
        $endpoint = "/me/drive/root:{$inputPath}:/thumbnails/0/medium/content";

        $this->logInfo('Getting conversion preview', [
            'input_path' => $inputPath,
            'format' => $outputFormat,
        ]);

        try {
            $response = $this->graphClient->api('GET', $endpoint);
            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get conversion preview: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Validate output format.
     */
    private function validateFormat(string $format): void
    {
        if (!$this->isFormatSupported($format)) {
            throw new ServiceException(
                'Unsupported output format: ' . $format .
                '. Supported formats: ' . implode(', ', $this->supportedFormats),
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Validate image format.
     */
    private function validateImageFormat(string $format): void
    {
        $imageFormats = ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'bmp'];

        if (!in_array(strtolower($format), $imageFormats, true)) {
            throw new ServiceException(
                'Unsupported image format: ' . $format .
                '. Supported formats: ' . implode(', ', $imageFormats),
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get conversion statistics.
     */
    public function getConversionStats(): array
    {
        // This would typically track conversion metrics
        // For now, return basic information
        return [
            'supported_formats' => $this->supportedFormats,
            'supported_input_formats' => $this->getSupportedInputFormats(),
            'last_conversion' => null, // Would track actual conversions
            'total_conversions' => 0,
        ];
    }

    /**
     * Convert with progress tracking (for large documents).
     */
    public function convertWithProgress(
        string $inputPath,
        string $outputFormat,
        callable $progressCallback = null,
        array $options = []
    ): string {
        // For Microsoft Graph, conversion is typically synchronous
        // This method provides a consistent interface for future async support

        if ($progressCallback) {
            $progressCallback(0, 'Starting conversion...');
        }

        try {
            $result = $this->convertDocument($inputPath, $outputFormat, $options);

            if ($progressCallback) {
                $progressCallback(100, 'Conversion completed');
            }

            return $result;
        } catch (ServiceException $e) {
            if ($progressCallback) {
                $progressCallback(-1, 'Conversion failed: ' . $e->getMessage());
            }
            throw $e;
        }
    }
}
