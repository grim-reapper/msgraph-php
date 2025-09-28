<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Services\CalendarService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $graphClient;
    private CalendarService $calendarService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphClient = Mockery::mock(GraphClient::class);
        $this->calendarService = new CalendarService($this->graphClient);
    }

    public function testGetEventsSuccess(): void
    {
        $expectedQuery = [
            '$top' => 50,
            '$select' => 'id,subject,start,end,organizer,attendees,location',
            '$orderby' => 'start/dateTime',
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me/events', ['query' => $expectedQuery])
            ->andReturn(new GraphResponse(['value' => [['subject' => 'Test Event']]]));

        $response = $this->calendarService->getEvents();
        $this->assertIsArray($response->getBody()['value']);
    }

    public function testCreateEventSuccess(): void
    {
        $eventData = [
            'subject' => 'Team Meeting',
            'start' => '2024-01-01T10:00:00',
            'end' => '2024-01-01T11:00:00',
            'location' => 'Conference Room 1',
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('POST', '/me/events', Mockery::on(function ($options) use ($eventData) {
                $body = json_decode($options['body'], true);
                return $body['subject'] === $eventData['subject'] &&
                       $body['location']['displayName'] === $eventData['location'];
            }))
            ->andReturn(new GraphResponse(['id' => 'event123']));

        $this->calendarService->createEvent($eventData);
    }

    public function testCreateEventThrowsExceptionOnMissingSubject(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Event subject is required');

        $eventData = [
            'start' => '2024-01-01T10:00:00',
            'end' => '2024-01-01T11:00:00',
        ];

        $this->calendarService->createEvent($eventData);
    }

    public function testDeleteEventSuccess(): void
    {
        $eventId = 'event123';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('DELETE', "/me/events/{$eventId}")
            ->andReturn(new GraphResponse([], 204));

        $result = $this->calendarService->deleteEvent($eventId);
        $this->assertTrue($result);
    }

    public function testAcceptEventSuccess(): void
    {
        $eventId = 'event123';
        $expectedBody = json_encode([
            'sendResponse' => true,
            'comment' => 'Sounds great!',
        ]);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('POST', "/me/events/{$eventId}/accept", ['body' => $expectedBody, 'headers' => ['Content-Type' => 'application/json']])
            ->andReturn(new GraphResponse([], 202));

        $this->calendarService->acceptEvent($eventId, 'Sounds great!');
    }

    public function testGetEventsThrowsServiceExceptionOnFailure(): void
    {
        $this->expectException(ServiceException::class);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->andThrow(new \GuzzleHttp\Exception\RequestException('Error', new \GuzzleHttp\Psr7\Request('GET', '/me/events')));

        $this->calendarService->getEvents();
    }
}
