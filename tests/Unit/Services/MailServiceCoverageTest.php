<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Services\MailService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class MailServiceCoverageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $graphClient;
    private MailService $mailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphClient = Mockery::mock(GraphClient::class);
        $this->mailService = new MailService($this->graphClient);
    }

    public function testGetMessageSuccess(): void
    {
        $messageId = 'message123';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', "/me/messages/{$messageId}")
            ->andReturn(new GraphResponse(['id' => $messageId, 'subject' => 'Test Subject']));

        $response = $this->mailService->getMessage($messageId);
        $this->assertSame($messageId, $response->getBody()['id']);
    }

    public function testMoveMessageSuccess(): void
    {
        $messageId = 'message123';
        $folderId = 'folder456';
        $expectedBody = json_encode(['destinationId' => $folderId]);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('POST', "/me/messages/{$messageId}/move", ['body' => $expectedBody, 'headers' => ['Content-Type' => 'application/json']])
            ->andReturn(new GraphResponse(['id' => $messageId]));

        $this->mailService->moveMessage($messageId, $folderId);
    }

    public function testMarkMessageAsReadSuccess(): void
    {
        $messageId = 'message123';
        $expectedBody = json_encode(['isRead' => true]);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('PATCH', "/me/messages/{$messageId}", ['body' => $expectedBody, 'headers' => ['Content-Type' => 'application/json']])
            ->andReturn(new GraphResponse([], 200));

        $this->mailService->markMessageAsRead($messageId);
    }
}
