<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClientInterface;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Services\OneDriveService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class OneDriveServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $graphClient;
    private OneDriveService $oneDriveService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphClient = Mockery::mock(GraphClientInterface::class);
        $this->oneDriveService = new OneDriveService($this->graphClient, new NullLogger());
    }

    public function testListFilesSuccessAtRoot(): void
    {
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me/drive/root:/children', ['query' => ['$top' => 50]])
            ->andReturn(new GraphResponse(['value' => [['name' => 'file.txt']]]));

        $response = $this->oneDriveService->listFiles('/', ['limit' => 50]);
        $this->assertIsArray($response->getBody()['value']);
    }

    public function testListFilesWithSubfolder(): void
    {
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me/drive/root:/folder/subfolder:/children', [])
            ->andReturn(new GraphResponse(['value' => []]));

        $this->oneDriveService->listFiles('/folder/subfolder');
    }

    public function testCreateFolderSuccess(): void
    {
        $path = '/Documents';
        $folderName = 'New Folder';
        $expectedBody = json_encode([
            'name' => $folderName,
            'folder' => new \stdClass(),
        ]);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('POST', '/me/drive/root:/Documents:/children', ['body' => $expectedBody, 'headers' => ['Content-Type' => 'application/json']])
            ->andReturn(new GraphResponse(['name' => $folderName]));

        $this->oneDriveService->createFolder($path, $folderName);
    }

    public function testDeleteItemSuccess(): void
    {
        $path = '/Documents/file.txt';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('DELETE', '/me/drive/root:/Documents/file.txt:')
            ->andReturn(new GraphResponse([], 204));

        $result = $this->oneDriveService->deleteItem($path);
        $this->assertTrue($result);
    }

    public function testDownloadFileSuccess(): void
    {
        $path = '/folder/file with spaces.txt';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me/drive/root:/folder/file%20with%20spaces.txt:/content')
            ->andReturn(new GraphResponse('file content'));

        $content = $this->oneDriveService->downloadFile($path);
        $this->assertSame('file content', $content);
    }
}
