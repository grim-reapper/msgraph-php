<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Services\CalendarService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CalendarServiceCoverageTest extends TestCase
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

    public function testGetCalendarEventsSuccess(): void
    {
        $calendarId = 'calendar123';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', "/me/calendars/{$calendarId}/events", Mockery::any())
            ->andReturn(new GraphResponse(['value' => [['subject' => 'Test Event']]]));

        $response = $this->calendarService->getCalendarEvents($calendarId);
        $this->assertIsArray($response->getBody()['value']);
    }

    public function testUpdateEventSuccess(): void
    {
        $eventId = 'event123';
        $eventData = ['location' => ['displayName' => 'New Location']];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('PATCH', "/me/events/{$eventId}", Mockery::any())
            ->andReturn(new GraphResponse(['id' => $eventId]));

        $this->calendarService->updateEvent($eventId, $eventData);
    }

    public function testDeclineEventSuccess(): void
    {
        $eventId = 'event123';
        $expectedBody = json_encode([
            'sendResponse' => false,
            'comment' => 'Sorry, cannot make it.',
        ]);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('POST', "/me/events/{$eventId}/decline", ['body' => $expectedBody, 'headers' => ['Content-Type' => 'application/json']])
            ->andReturn(new GraphResponse([], 202));

        $this->calendarService->declineEvent($eventId, 'Sorry, cannot make it.', false);
    }
}
