<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Services\DocumentService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DocumentServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $graphClient;
    private DocumentService $documentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphClient = Mockery::mock(GraphClient::class);
        $this->documentService = new DocumentService($this->graphClient);
    }

    public function testConvertDocumentSuccess(): void
    {
        $inputPath = '/test.docx';
        $outputFormat = 'pdf';
        $expectedEndpoint = "/me/drive/root:{$inputPath}:/content?format={$outputFormat}";

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', $expectedEndpoint, [])
            ->andReturn(new GraphResponse('pdf_content'));

        $result = $this->documentService->convertDocument($inputPath, $outputFormat);
        $this->assertSame('pdf_content', $result);
    }

    public function testConvertDocumentThrowsExceptionForUnsupportedFormat(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Unsupported output format/');

        $this->documentService->convertDocument('/test.docx', 'unsupported_format');
    }

    public function testConvertDocxToPdfHelper(): void
    {
        $inputPath = '/test.docx';
        $expectedEndpoint = "/me/drive/root:{$inputPath}:/content?format=pdf";

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', $expectedEndpoint, [])
            ->andReturn(new GraphResponse('pdf_content'));

        $this->documentService->convertDocxToPdf($inputPath);
    }

    public function testConvertDocumentThrowsServiceExceptionOnFailure(): void
    {
        $this->expectException(ServiceException::class);

        $inputPath = '/test.docx';
        $outputFormat = 'pdf';
        $expectedEndpoint = "/me/drive/root:{$inputPath}:/content?format={$outputFormat}";

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', $expectedEndpoint, [])
            ->andThrow(new \GuzzleHttp\Exception\RequestException('Error', new \GuzzleHttp\Psr7\Request('GET', $expectedEndpoint)));

        $this->documentService->convertDocument($inputPath, $outputFormat);
    }
}
