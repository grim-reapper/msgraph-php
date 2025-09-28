<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Services;

use DateTime;
use DateTimeZone;
use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Traits\HasLogging;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for Microsoft Graph calendar operations.
 */
final class CalendarService
{
    use HasLogging;

    private GraphClient $graphClient;

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
     * Get user's calendars.
     */
    public function getCalendars(): GraphResponse
    {
        $endpoint = '/me/calendars';

        $this->logInfo('Getting user calendars');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get calendars: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get default calendar.
     */
    public function getDefaultCalendar(): GraphResponse
    {
        $endpoint = '/me/calendar';

        $this->logInfo('Getting default calendar');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get default calendar: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get events from default calendar.
     */
    public function getEvents(array $options = []): GraphResponse
    {
        $endpoint = '/me/events';

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
            '$select' => $options['select'] ?? 'id,subject,start,end,organizer,attendees,location',
            '$orderby' => $options['orderby'] ?? 'start/dateTime',
        ], $options['query'] ?? []);

        $this->logInfo('Getting events', ['limit' => $queryParams['$top']]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get events: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get a specific event.
     */
    public function getEvent(string $eventId): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}";

        $this->logInfo('Getting event', ['event_id' => $eventId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get event: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Create a new event.
     */
    public function createEvent(array $eventData): GraphResponse
    {
        $this->validateEventData($eventData);

        $endpoint = '/me/events';
        $event = $this->buildEventData($eventData);

        $this->logInfo('Creating event', [
            'subject' => $eventData['subject'] ?? '',
            'start' => $eventData['start'] ?? '',
            'end' => $eventData['end'] ?? '',
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($event),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Event created successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create event: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update an event.
     */
    public function updateEvent(string $eventId, array $eventData): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}";
        $event = $this->buildEventData($eventData);

        $this->logInfo('Updating event', ['event_id' => $eventId]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($event),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Event updated successfully', ['event_id' => $eventId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update event: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(string $eventId): bool
    {
        $endpoint = "/me/events/{$eventId}";

        $this->logInfo('Deleting event', ['event_id' => $eventId]);

        try {
            $this->graphClient->api('DELETE', $endpoint);
            $this->logInfo('Event deleted successfully', ['event_id' => $eventId]);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to delete event: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get events from a specific calendar.
     */
    public function getCalendarEvents(string $calendarId, array $options = []): GraphResponse
    {
        $endpoint = "/me/calendars/{$calendarId}/events";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
            '$select' => $options['select'] ?? 'id,subject,start,end,organizer,attendees',
        ], $options['query'] ?? []);

        $this->logInfo('Getting calendar events', [
            'calendar_id' => $calendarId,
            'limit' => $queryParams['$top'],
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get calendar events: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get today's events.
     */
    public function getTodaysEvents(array $options = []): GraphResponse
    {
        $today = date('Y-m-d');
        $startOfDay = $today . 'T00:00:00Z';
        $endOfDay = $today . 'T23:59:59Z';

        return $this->getEventsFromRange($startOfDay, $endOfDay, $options);
    }

    /**
     * Get events from date range.
     */
    public function getEventsFromRange(string $startDateTime, string $endDateTime, array $options = []): GraphResponse
    {
        $endpoint = '/me/events';

        $queryParams = array_merge([
            '$filter' => "start/dateTime ge '{$startDateTime}' and end/dateTime le '{$endDateTime}'",
            '$top' => $options['limit'] ?? 50,
            '$orderby' => 'start/dateTime',
        ], $options['query'] ?? []);

        $this->logInfo('Getting events from date range', [
            'start' => $startDateTime,
            'end' => $endDateTime,
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get events from date range: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get upcoming events.
     */
    public function getUpcomingEvents(int $days = 7, array $options = []): GraphResponse
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $future = new DateTime("+{$days} days", new DateTimeZone('UTC'));

        return $this->getEventsFromRange(
            $now->format('Y-m-d\TH:i:s\Z'),
            $future->format('Y-m-d\TH:i:s\Z'),
            $options
        );
    }

    /**
     * Accept event invitation.
     */
    public function acceptEvent(string $eventId, string $comment = '', bool $sendResponse = true): GraphResponse
    {
        return $this->respondToEvent($eventId, 'accept', $comment, $sendResponse);
    }

    /**
     * Decline event invitation.
     */
    public function declineEvent(string $eventId, string $comment = '', bool $sendResponse = true): GraphResponse
    {
        return $this->respondToEvent($eventId, 'decline', $comment, $sendResponse);
    }

    /**
     * Tentatively accept event invitation.
     */
    public function tentativelyAcceptEvent(string $eventId, string $comment = '', bool $sendResponse = true): GraphResponse
    {
        return $this->respondToEvent($eventId, 'tentativelyAccept', $comment, $sendResponse);
    }

    /**
     * Respond to event invitation.
     */
    private function respondToEvent(string $eventId, string $response, string $comment = '', bool $sendResponse = true): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}/{$response}";
        $responseData = [
            'sendResponse' => $sendResponse,
        ];

        if (!empty($comment)) {
            $responseData['comment'] = $comment;
        }

        $this->logInfo('Responding to event', [
            'event_id' => $eventId,
            'response' => $response,
            'comment' => $comment,
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($responseData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Event response sent successfully', [
                'event_id' => $eventId,
                'response' => $response,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to respond to event: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get event attendees.
     */
    public function getEventAttendees(string $eventId): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}/attendees";

        $this->logInfo('Getting event attendees', ['event_id' => $eventId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get event attendees: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Add attendee to event.
     */
    public function addEventAttendee(string $eventId, array $attendeeData): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}/attendees";
        $attendee = $this->buildAttendeeData($attendeeData);

        $this->logInfo('Adding event attendee', [
            'event_id' => $eventId,
            'email' => $attendeeData['email'] ?? '',
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($attendee),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Event attendee added successfully', ['event_id' => $eventId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to add event attendee: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Remove attendee from event.
     */
    public function removeEventAttendee(string $eventId, string $attendeeEmail): bool
    {
        // First get the event to find the attendee
        $event = $this->getEvent($eventId);
        $attendees = $event->get('attendees', []);

        $attendeeToRemove = null;
        foreach ($attendees as $attendee) {
            if (($attendee['emailAddress']['address'] ?? '') === $attendeeEmail) {
                $attendeeToRemove = $attendee;
                break;
            }
        }

        if (!$attendeeToRemove) {
            throw new ServiceException(
                'Attendee not found: ' . $attendeeEmail,
                0,
                404,
                ServiceException::ITEM_NOT_FOUND
            );
        }

        $endpoint = "/me/events/{$eventId}/attendees/" . $attendeeToRemove['id'];

        $this->logInfo('Removing event attendee', [
            'event_id' => $eventId,
            'email' => $attendeeEmail,
        ]);

        try {
            $this->graphClient->api('DELETE', $endpoint);
            $this->logInfo('Event attendee removed successfully', ['event_id' => $eventId]);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to remove event attendee: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Create a new calendar.
     */
    public function createCalendar(array $calendarData): GraphResponse
    {
        $this->validateCalendarData($calendarData);

        $endpoint = '/me/calendars';

        $this->logInfo('Creating calendar', ['name' => $calendarData['name'] ?? '']);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($calendarData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Calendar created successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create calendar: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update calendar.
     */
    public function updateCalendar(string $calendarId, array $calendarData): GraphResponse
    {
        $endpoint = "/me/calendars/{$calendarId}";

        $this->logInfo('Updating calendar', ['calendar_id' => $calendarId]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($calendarData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Calendar updated successfully', ['calendar_id' => $calendarId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update calendar: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Delete calendar.
     */
    public function deleteCalendar(string $calendarId): bool
    {
        $endpoint = "/me/calendars/{$calendarId}";

        $this->logInfo('Deleting calendar', ['calendar_id' => $calendarId]);

        try {
            $this->graphClient->api('DELETE', $endpoint);
            $this->logInfo('Calendar deleted successfully', ['calendar_id' => $calendarId]);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to delete calendar: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get calendar permissions.
     */
    public function getCalendarPermissions(string $calendarId): GraphResponse
    {
        $endpoint = "/me/calendars/{$calendarId}/calendarPermissions";

        $this->logInfo('Getting calendar permissions', ['calendar_id' => $calendarId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get calendar permissions: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Add calendar permission.
     */
    public function addCalendarPermission(string $calendarId, array $permissionData): GraphResponse
    {
        $endpoint = "/me/calendars/{$calendarId}/calendarPermissions";

        $this->logInfo('Adding calendar permission', [
            'calendar_id' => $calendarId,
            'role' => $permissionData['role'] ?? '',
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($permissionData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Calendar permission added successfully', ['calendar_id' => $calendarId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to add calendar permission: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get free/busy schedule.
     */
    public function getFreeBusySchedule(array $userIds, string $startTime, string $endTime): GraphResponse
    {
        $endpoint = '/me/calendar/getSchedule';
        $scheduleData = [
            'schedules' => array_map(fn ($userId) => ['userId' => $userId], $userIds),
            'startTime' => [
                'dateTime' => $startTime,
                'timeZone' => 'UTC',
            ],
            'endTime' => [
                'dateTime' => $endTime,
                'timeZone' => 'UTC',
            ],
        ];

        $this->logInfo('Getting free/busy schedule', [
            'user_count' => count($userIds),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($scheduleData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get free/busy schedule: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Find meeting times.
     */
    public function findMeetingTimes(array $attendees, array $timeConstraint, array $options = []): GraphResponse
    {
        $endpoint = '/me/findMeetingTimes';
        $meetingData = array_merge([
            'attendees' => $this->buildAttendeesForMeeting($attendees),
            'timeConstraint' => $timeConstraint,
        ], $options);

        $this->logInfo('Finding meeting times', [
            'attendees' => count($attendees),
        ]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($meetingData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to find meeting times: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get calendar view (events in date range).
     */
    public function getCalendarView(string $startDateTime, string $endDateTime, array $options = []): GraphResponse
    {
        $endpoint = '/me/calendarView';

        $queryParams = array_merge([
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            '$top' => $options['limit'] ?? 50,
            '$orderby' => 'start/dateTime',
        ], $options['query'] ?? []);

        $this->logInfo('Getting calendar view', [
            'start' => $startDateTime,
            'end' => $endDateTime,
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get calendar view: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get events by date.
     */
    public function getEventsByDate(string $date, array $options = []): GraphResponse
    {
        $startOfDay = $date . 'T00:00:00Z';
        $endOfDay = $date . 'T23:59:59Z';

        return $this->getEventsFromRange($startOfDay, $endOfDay, $options);
    }

    /**
     * Get events for current week.
     */
    public function getCurrentWeekEvents(array $options = []): GraphResponse
    {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $sunday = date('Y-m-d', strtotime('sunday this week'));

        return $this->getEventsFromRange(
            $monday . 'T00:00:00Z',
            $sunday . 'T23:59:59Z',
            $options
        );
    }

    /**
     * Get events for current month.
     */
    public function getCurrentMonthEvents(array $options = []): GraphResponse
    {
        $firstDay = date('Y-m-01');
        $lastDay = date('Y-m-t');

        return $this->getEventsFromRange(
            $firstDay . 'T00:00:00Z',
            $lastDay . 'T23:59:59Z',
            $options
        );
    }

    /**
     * Search events.
     */
    public function searchEvents(string $query, array $options = []): GraphResponse
    {
        $endpoint = '/me/events';

        $queryParams = array_merge([
            '$search' => '"' . $query . '"',
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Searching events', ['query' => $query]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to search events: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get event instances (recurring events).
     */
    public function getEventInstances(string $eventId, string $startDateTime, string $endDateTime): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}/instances";

        $queryParams = [
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
        ];

        $this->logInfo('Getting event instances', [
            'event_id' => $eventId,
            'start' => $startDateTime,
            'end' => $endDateTime,
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get event instances: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Cancel event.
     */
    public function cancelEvent(string $eventId, string $comment = ''): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}/cancel";
        $cancelData = [];

        if (!empty($comment)) {
            $cancelData['comment'] = $comment;
        }

        $this->logInfo('Canceling event', ['event_id' => $eventId]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($cancelData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Event canceled successfully', ['event_id' => $eventId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to cancel event: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Validate event data.
     */
    private function validateEventData(array $eventData): void
    {
        if (empty($eventData['subject'] ?? '')) {
            throw new ServiceException(
                'Event subject is required',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        if (empty($eventData['start'] ?? '')) {
            throw new ServiceException(
                'Event start time is required',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        if (empty($eventData['end'] ?? '')) {
            throw new ServiceException(
                'Event end time is required',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Validate calendar data.
     */
    private function validateCalendarData(array $calendarData): void
    {
        if (empty($calendarData['name'] ?? '')) {
            throw new ServiceException(
                'Calendar name is required',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Build event data structure.
     */
    private function buildEventData(array $eventData): array
    {
        $event = [
            'subject' => $eventData['subject'],
            'start' => [
                'dateTime' => $eventData['start'],
                'timeZone' => $eventData['timezone'] ?? 'UTC',
            ],
            'end' => [
                'dateTime' => $eventData['end'],
                'timeZone' => $eventData['timezone'] ?? 'UTC',
            ],
        ];

        if (!empty($eventData['location'])) {
            $event['location'] = [
                'displayName' => $eventData['location'],
            ];
        }

        if (!empty($eventData['body'])) {
            $event['body'] = [
                'contentType' => $eventData['content_type'] ?? 'HTML',
                'content' => $eventData['body'],
            ];
        }

        if (!empty($eventData['attendees'])) {
            $event['attendees'] = $this->buildAttendeesForEvent($eventData['attendees']);
        }

        if (!empty($eventData['isAllDay'])) {
            $event['isAllDay'] = $eventData['isAllDay'];
        }

        if (!empty($eventData['showAs'])) {
            $event['showAs'] = $eventData['showAs'];
        }

        if (!empty($eventData['isReminderOn'])) {
            $event['isReminderOn'] = $eventData['isReminderOn'];
        }

        if (!empty($eventData['reminderMinutesBeforeStart'])) {
            $event['reminderMinutesBeforeStart'] = $eventData['reminderMinutesBeforeStart'];
        }

        return $event;
    }

    /**
     * Build attendees for event.
     */
    private function buildAttendeesForEvent(array $attendees): array
    {
        return array_map(function ($attendee) {
            $attendeeData = [
                'emailAddress' => [
                    'address' => $attendee['email'],
                ],
                'type' => $attendee['type'] ?? 'required',
            ];

            if (!empty($attendee['name'])) {
                $attendeeData['emailAddress']['name'] = $attendee['name'];
            }

            return $attendeeData;
        }, $attendees);
    }

    /**
     * Build attendee data.
     */
    private function buildAttendeeData(array $attendeeData): array
    {
        return [
            'emailAddress' => [
                'address' => $attendeeData['email'],
                'name' => $attendeeData['name'] ?? '',
            ],
            'type' => $attendeeData['type'] ?? 'required',
        ];
    }

    /**
     * Build attendees for meeting time finder.
     */
    private function buildAttendeesForMeeting(array $attendees): array
    {
        return array_map(fn ($attendee) => [
                'emailAddress' => [
                    'address' => $attendee['email'],
                    'name' => $attendee['name'] ?? '',
                ],
                'type' => $attendee['type'] ?? 'required',
            ], $attendees);
    }

    /**
     * Get calendar groups.
     */
    public function getCalendarGroups(): GraphResponse
    {
        $endpoint = '/me/calendarGroups';

        $this->logInfo('Getting calendar groups');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get calendar groups: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Create calendar group.
     */
    public function createCalendarGroup(string $name): GraphResponse
    {
        $endpoint = '/me/calendarGroups';

        $this->logInfo('Creating calendar group', ['name' => $name]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode(['name' => $name]),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Calendar group created successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create calendar group: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get events with attachments.
     */
    public function getEventsWithAttachments(array $options = []): GraphResponse
    {
        $endpoint = '/me/events';

        $queryParams = array_merge([
            '$filter' => 'hasAttachments eq true',
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Getting events with attachments');

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get events with attachments: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get event attachments.
     */
    public function getEventAttachments(string $eventId): GraphResponse
    {
        $endpoint = "/me/events/{$eventId}/attachments";

        $this->logInfo('Getting event attachments', ['event_id' => $eventId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get event attachments: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Download event attachment.
     */
    public function downloadEventAttachment(string $eventId, string $attachmentId): string
    {
        $endpoint = "/me/events/{$eventId}/attachments/{$attachmentId}/\$value";

        $this->logInfo('Downloading event attachment', [
            'event_id' => $eventId,
            'attachment_id' => $attachmentId,
        ]);

        try {
            $response = $this->graphClient->api('GET', $endpoint);
            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to download event attachment: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get user's availability.
     */
    public function getUserAvailability(array $userIds, array $timeConstraints): GraphResponse
    {
        $endpoint = '/me/calendar/getSchedule';
        $availabilityData = [
            'schedules' => array_map(fn ($userId) => ['userId' => $userId], $userIds),
            'startTime' => $timeConstraints['start'],
            'endTime' => $timeConstraints['end'],
        ];

        $this->logInfo('Getting user availability', [
            'user_count' => count($userIds),
        ]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($availabilityData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user availability: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get calendar events count.
     */
    public function getEventsCount(string $startDateTime, string $endDateTime): int
    {
        $events = $this->getEventsFromRange($startDateTime, $endDateTime, [
            'query' => [
                '$top' => 1,
                '$count' => 'true',
            ],
        ]);

        return $events->get('@odata.count', 0);
    }

    /**
     * Get busy time.
     */
    public function getBusyTime(string $startDateTime, string $endDateTime): GraphResponse
    {
        $endpoint = '/me/calendarView';

        $queryParams = [
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            '$select' => 'start,end,showAs',
            '$filter' => 'showAs eq \'busy\'',
        ];

        $this->logInfo('Getting busy time', [
            'start' => $startDateTime,
            'end' => $endDateTime,
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get busy time: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get working hours.
     */
    public function getWorkingHours(): GraphResponse
    {
        $endpoint = '/me/mailboxSettings';

        $this->logInfo('Getting working hours');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get working hours: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update working hours.
     */
    public function updateWorkingHours(array $workingHours): GraphResponse
    {
        $endpoint = '/me/mailboxSettings';

        $this->logInfo('Updating working hours');

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($workingHours),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Working hours updated successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update working hours: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }
}
