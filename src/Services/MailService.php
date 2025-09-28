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
 * Service for Microsoft Graph mail operations.
 */
final class MailService
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
     * Send an email.
     */
    public function sendEmail(array $emailData): GraphResponse
    {
        $this->validateEmailData($emailData);

        $endpoint = '/me/sendMail';
        $message = $this->buildMessageData($emailData);

        $this->logInfo('Sending email', [
            'to' => $emailData['to'] ?? [],
            'subject' => $emailData['subject'] ?? '',
            'has_attachments' => !empty($emailData['attachments'] ?? []),
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($message),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Email sent successfully', [
                'to' => $emailData['to'] ?? [],
                'subject' => $emailData['subject'] ?? '',
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to send email: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get messages from inbox.
     */
    public function getMessages(array $options = []): GraphResponse
    {
        $endpoint = '/me/messages';

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
            '$select' => $options['select'] ?? 'id,subject,from,to,createdDateTime,hasAttachments',
            '$orderby' => $options['orderby'] ?? 'createdDateTime desc',
        ], $options['query'] ?? []);

        $this->logInfo('Getting messages', ['limit' => $queryParams['$top']]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get messages: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get a specific message.
     */
    public function getMessage(string $messageId): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}";

        $this->logInfo('Getting message', ['message_id' => $messageId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get message attachments.
     */
    public function getMessageAttachments(string $messageId): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/attachments";

        $this->logInfo('Getting message attachments', ['message_id' => $messageId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get message attachments: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Download attachment content.
     */
    public function downloadAttachment(string $messageId, string $attachmentId): string
    {
        $endpoint = "/me/messages/{$messageId}/attachments/{$attachmentId}/\$value";

        $this->logInfo('Downloading attachment', [
            'message_id' => $messageId,
            'attachment_id' => $attachmentId,
        ]);

        try {
            $response = $this->graphClient->api('GET', $endpoint);
            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to download attachment: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Create a draft message.
     */
    public function createDraft(array $emailData): GraphResponse
    {
        $this->validateEmailData($emailData);

        $endpoint = '/me/messages';
        $message = $this->buildMessageData($emailData);

        $this->logInfo('Creating draft message', [
            'to' => $emailData['to'] ?? [],
            'subject' => $emailData['subject'] ?? '',
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($message),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Draft message created successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create draft: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update a draft message.
     */
    public function updateDraft(string $messageId, array $emailData): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}";
        $message = $this->buildMessageData($emailData);

        $this->logInfo('Updating draft message', ['message_id' => $messageId]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($message),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Draft message updated successfully', ['message_id' => $messageId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update draft: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Send a draft message.
     */
    public function sendDraft(string $messageId): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/send";

        $this->logInfo('Sending draft message', ['message_id' => $messageId]);

        try {
            $response = $this->graphClient->api('POST', $endpoint);

            $this->logInfo('Draft message sent successfully', ['message_id' => $messageId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to send draft: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(string $messageId): bool
    {
        $endpoint = "/me/messages/{$messageId}";

        $this->logInfo('Deleting message', ['message_id' => $messageId]);

        try {
            $this->graphClient->api('DELETE', $endpoint);
            $this->logInfo('Message deleted successfully', ['message_id' => $messageId]);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to delete message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Move message to folder.
     */
    public function moveMessage(string $messageId, string $folderId): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/move";
        $moveData = [
            'destinationId' => $folderId,
        ];

        $this->logInfo('Moving message', [
            'message_id' => $messageId,
            'folder_id' => $folderId,
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($moveData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Message moved successfully', [
                'message_id' => $messageId,
                'folder_id' => $folderId,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to move message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Copy message to folder.
     */
    public function copyMessage(string $messageId, string $folderId): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/copy";
        $copyData = [
            'destinationId' => $folderId,
        ];

        $this->logInfo('Copying message', [
            'message_id' => $messageId,
            'folder_id' => $folderId,
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($copyData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Message copied successfully', [
                'message_id' => $messageId,
                'folder_id' => $folderId,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to copy message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get mail folders.
     */
    public function getMailFolders(): GraphResponse
    {
        $endpoint = '/me/mailFolders';

        $this->logInfo('Getting mail folders');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get mail folders: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get messages from a specific folder.
     */
    public function getFolderMessages(string $folderId, array $options = []): GraphResponse
    {
        $endpoint = "/me/mailFolders/{$folderId}/messages";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
            '$select' => $options['select'] ?? 'id,subject,from,to,createdDateTime,hasAttachments',
        ], $options['query'] ?? []);

        $this->logInfo('Getting folder messages', [
            'folder_id' => $folderId,
            'limit' => $queryParams['$top'],
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get folder messages: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Create a new mail folder.
     */
    public function createMailFolder(string $name, string $parentFolderId = null): GraphResponse
    {
        $endpoint = '/me/mailFolders';
        $folderData = [
            'displayName' => $name,
        ];

        if ($parentFolderId) {
            $folderData['parentFolderId'] = $parentFolderId;
        }

        $this->logInfo('Creating mail folder', [
            'name' => $name,
            'parent_folder_id' => $parentFolderId,
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($folderData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Mail folder created successfully', ['name' => $name]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create mail folder: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Reply to a message.
     */
    public function replyToMessage(string $messageId, string $comment = '', array $options = []): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/reply";
        $replyData = [];

        if (!empty($comment)) {
            $replyData['comment'] = $comment;
        }

        if (!empty($options['toRecipients'])) {
            $replyData['toRecipients'] = $this->buildRecipients($options['toRecipients']);
        }

        $this->logInfo('Replying to message', ['message_id' => $messageId]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($replyData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Reply sent successfully', ['message_id' => $messageId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to reply to message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Reply all to a message.
     */
    public function replyAllToMessage(string $messageId, string $comment = '', array $options = []): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/replyAll";
        $replyData = [];

        if (!empty($comment)) {
            $replyData['comment'] = $comment;
        }

        if (!empty($options['toRecipients'])) {
            $replyData['toRecipients'] = $this->buildRecipients($options['toRecipients']);
        }

        $this->logInfo('Replying all to message', ['message_id' => $messageId]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($replyData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Reply all sent successfully', ['message_id' => $messageId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to reply all to message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Forward a message.
     */
    public function forwardMessage(string $messageId, array $recipients, string $comment = ''): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}/forward";
        $forwardData = [
            'toRecipients' => $this->buildRecipients($recipients),
        ];

        if (!empty($comment)) {
            $forwardData['comment'] = $comment;
        }

        $this->logInfo('Forwarding message', [
            'message_id' => $messageId,
            'recipients' => count($recipients),
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($forwardData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Message forwarded successfully', ['message_id' => $messageId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to forward message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Mark message as read.
     */
    public function markMessageAsRead(string $messageId): GraphResponse
    {
        return $this->updateMessageReadStatus($messageId, true);
    }

    /**
     * Mark message as unread.
     */
    public function markMessageAsUnread(string $messageId): GraphResponse
    {
        return $this->updateMessageReadStatus($messageId, false);
    }

    /**
     * Update message read status.
     */
    private function updateMessageReadStatus(string $messageId, bool $isRead): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}";

        $this->logInfo('Updating message read status', [
            'message_id' => $messageId,
            'is_read' => $isRead,
        ]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode([
                    'isRead' => $isRead,
                ]),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Message read status updated successfully', [
                'message_id' => $messageId,
                'is_read' => $isRead,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update message read status: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Search messages.
     */
    public function searchMessages(string $query, array $options = []): GraphResponse
    {
        $endpoint = '/me/messages';

        $queryParams = array_merge([
            '$search' => '"' . $query . '"',
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Searching messages', ['query' => $query]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to search messages: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadMessagesCount(): int
    {
        $messages = $this->getMessages([
            'query' => [
                '$filter' => 'isRead eq false',
                '$top' => 1,
                '$count' => 'true',
            ],
        ]);

        return $messages->get('@odata.count', 0);
    }

    /**
     * Get messages with attachments.
     */
    public function getMessagesWithAttachments(array $options = []): GraphResponse
    {
        $endpoint = '/me/messages';

        $queryParams = array_merge([
            '$filter' => 'hasAttachments eq true',
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Getting messages with attachments');

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get messages with attachments: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get messages from today.
     */
    public function getTodaysMessages(array $options = []): GraphResponse
    {
        $today = date('Y-m-d');
        $startOfDay = $today . 'T00:00:00Z';
        $endOfDay = $today . 'T23:59:59Z';

        $endpoint = '/me/messages';

        $queryParams = array_merge([
            '$filter' => "createdDateTime ge {$startOfDay} and createdDateTime le {$endOfDay}",
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Getting today\'s messages');

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get today\'s messages: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get messages from date range.
     */
    public function getMessagesFromRange(string $startDate, string $endDate, array $options = []): GraphResponse
    {
        $startDateTime = $startDate . 'T00:00:00Z';
        $endDateTime = $endDate . 'T23:59:59Z';

        $endpoint = '/me/messages';

        $queryParams = array_merge([
            '$filter' => "createdDateTime ge {$startDateTime} and createdDateTime le {$endDateTime}",
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Getting messages from date range', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get messages from date range: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Validate email data.
     */
    private function validateEmailData(array $emailData): void
    {
        if (empty($emailData['to'] ?? []) && empty($emailData['cc'] ?? []) && empty($emailData['bcc'] ?? [])) {
            throw new ServiceException(
                'Email must have at least one recipient (to, cc, or bcc)',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        if (empty($emailData['subject'] ?? '')) {
            throw new ServiceException(
                'Email subject is required',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        if (empty($emailData['body'] ?? '')) {
            throw new ServiceException(
                'Email body is required',
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Build message data structure.
     */
    private function buildMessageData(array $emailData): array
    {
        $message = [
            'subject' => $emailData['subject'],
            'body' => [
                'contentType' => $emailData['content_type'] ?? 'HTML',
                'content' => $emailData['body'],
            ],
        ];

        // Add recipients
        if (!empty($emailData['to'] ?? [])) {
            $message['toRecipients'] = $this->buildRecipients($emailData['to']);
        }

        if (!empty($emailData['cc'] ?? [])) {
            $message['ccRecipients'] = $this->buildRecipients($emailData['cc']);
        }

        if (!empty($emailData['bcc'] ?? [])) {
            $message['bccRecipients'] = $this->buildRecipients($emailData['bcc']);
        }

        // Add attachments
        if (!empty($emailData['attachments'] ?? [])) {
            $message['attachments'] = $this->buildAttachments($emailData['attachments']);
        }

        return ['message' => $message];
    }

    /**
     * Build recipients array.
     */
    private function buildRecipients(array $recipients): array
    {
        $result = [];

        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $result[] = [
                    'emailAddress' => [
                        'address' => $recipient,
                    ],
                ];
            } elseif (is_array($recipient)) {
                $result[] = [
                    'emailAddress' => [
                        'address' => $recipient['email'],
                        'name' => $recipient['name'] ?? '',
                    ],
                ];
            }
        }

        return $result;
    }

    /**
     * Build attachments array.
     */
    private function buildAttachments(array $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $attachmentData = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment['name'],
                'contentBytes' => base64_encode($attachment['content']),
            ];

            if (!empty($attachment['content_type'])) {
                $attachmentData['contentType'] = $attachment['content_type'];
            }

            $result[] = $attachmentData;
        }

        return $result;
    }

    /**
     * Get message rules.
     */
    public function getMessageRules(): GraphResponse
    {
        $endpoint = '/me/mailFolders/inbox/messageRules';

        $this->logInfo('Getting message rules');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get message rules: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Create a message rule.
     */
    public function createMessageRule(array $ruleData): GraphResponse
    {
        $endpoint = '/me/mailFolders/inbox/messageRules';

        $this->logInfo('Creating message rule', ['name' => $ruleData['displayName'] ?? '']);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($ruleData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Message rule created successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create message rule: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get message categories.
     */
    public function getCategories(): GraphResponse
    {
        $endpoint = '/me/outlook/masterCategories';

        $this->logInfo('Getting message categories');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get categories: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Add category to message.
     */
    public function addMessageCategory(string $messageId, string $categoryId): GraphResponse
    {
        $endpoint = "/me/messages/{$messageId}";

        $this->logInfo('Adding category to message', [
            'message_id' => $messageId,
            'category_id' => $categoryId,
        ]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode([
                    'categories' => [$categoryId],
                ]),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Category added to message successfully', [
                'message_id' => $messageId,
                'category_id' => $categoryId,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to add category to message: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get focused inbox settings.
     */
    public function getFocusedInboxSettings(): GraphResponse
    {
        $endpoint = '/me/mailboxSettings';

        $this->logInfo('Getting focused inbox settings');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get focused inbox settings: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update focused inbox settings.
     */
    public function updateFocusedInboxSettings(array $settings): GraphResponse
    {
        $endpoint = '/me/mailboxSettings';

        $this->logInfo('Updating focused inbox settings');

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($settings),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Focused inbox settings updated successfully');

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update focused inbox settings: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }
}
