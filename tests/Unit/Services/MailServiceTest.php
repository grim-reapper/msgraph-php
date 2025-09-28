<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Services\MailService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class MailServiceTest extends TestCase
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

    public function testSendEmailSuccess(): void
    {
        $emailData = [
            'to' => ['test@example.com'],
            'subject' => 'Test Subject',
            'body' => '<p>Test Body</p>',
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('POST', '/me/sendMail', Mockery::on(function ($options) use ($emailData) {
                $body = json_decode($options['body'], true);
                return $body['message']['subject'] === $emailData['subject'] &&
                       $body['message']['toRecipients'][0]['emailAddress']['address'] === $emailData['to'][0];
            }))
            ->andReturn(new GraphResponse([], 202));

        $this->mailService->sendEmail($emailData);
    }

    public function testSendEmailThrowsExceptionOnMissingSubject(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Email subject is required');

        $emailData = [
            'to' => ['test@example.com'],
            'body' => '<p>Test Body</p>',
        ];

        $this->mailService->sendEmail($emailData);
    }

    public function testGetMessagesSuccess(): void
    {
        $expectedQuery = [
            '$top' => 50,
            '$select' => 'id,subject,from,to,createdDateTime,hasAttachments',
            '$orderby' => 'createdDateTime desc',
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me/messages', ['query' => $expectedQuery])
            ->andReturn(new GraphResponse(['value' => [['subject' => 'Test Email']]]));

        $response = $this->mailService->getMessages();
        $this->assertIsArray($response->getBody()['value']);
    }

    public function testDeleteMessageSuccess(): void
    {
        $messageId = 'message123';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('DELETE', "/me/messages/{$messageId}")
            ->andReturn(new GraphResponse([], 204));

        $result = $this->mailService->deleteMessage($messageId);
        $this->assertTrue($result);
    }

    public function testSendEmailThrowsServiceExceptionOnFailure(): void
    {
        $this->expectException(ServiceException::class);

        $emailData = [
            'to' => ['test@example.com'],
            'subject' => 'Test Subject',
            'body' => '<p>Test Body</p>',
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->andThrow(new \GuzzleHttp\Exception\RequestException('Error', new \GuzzleHttp\Psr7\Request('POST', '/me/sendMail')));

        $this->mailService->sendEmail($emailData);
    }
}
