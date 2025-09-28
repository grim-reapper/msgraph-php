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
 * Service for Microsoft Graph user operations.
 */
final class UserService
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
     * Get current user profile.
     */
    public function getCurrentUser(): GraphResponse
    {
        $endpoint = '/me';

        $this->logInfo('Getting current user profile');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get current user: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user by ID.
     */
    public function getUser(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}";

        $this->logInfo('Getting user by ID', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Search for users.
     */
    public function searchUsers(string $query, array $options = []): GraphResponse
    {
        $endpoint = '/users';

        $queryParams = array_merge([
            '$search' => '"' . $query . '"',
            '$top' => $options['limit'] ?? 25,
        ], $options['query'] ?? []);

        $this->logInfo('Searching users', ['query' => $query, 'limit' => $queryParams['$top']]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to search users: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's profile photo.
     */
    public function getUserPhoto(string $userId, string $size = 'medium'): string
    {
        $endpoint = "/users/{$userId}/photo/\$value";

        $this->logInfo('Getting user photo', ['user_id' => $userId, 'size' => $size]);

        try {
            $response = $this->graphClient->api('GET', $endpoint);
            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user photo: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get current user's profile photo.
     */
    public function getMyPhoto(string $size = 'medium'): string
    {
        return $this->getUserPhoto('me', $size);
    }

    /**
     * Get user's photo metadata.
     */
    public function getUserPhotoMetadata(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/photo";

        $this->logInfo('Getting user photo metadata', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user photo metadata: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Update user's profile photo.
     */
    public function updateUserPhoto(string $userId, string $photoPath): GraphResponse
    {
        if (!file_exists($photoPath)) {
            throw new ServiceException(
                'Photo file does not exist: ' . $photoPath,
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        $endpoint = "/users/{$userId}/photo/\$value";

        $this->logInfo('Updating user photo', ['user_id' => $userId, 'photo_path' => $photoPath]);

        try {
            $response = $this->graphClient->api('PUT', $endpoint, [
                'body' => fopen($photoPath, 'rb'),
                'headers' => [
                    'Content-Type' => 'image/jpeg', // Microsoft Graph expects JPEG
                ],
            ]);

            $this->logInfo('User photo updated successfully', ['user_id' => $userId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update user photo: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Delete user's profile photo.
     */
    public function deleteUserPhoto(string $userId): bool
    {
        $endpoint = "/users/{$userId}/photo/\$value";

        $this->logInfo('Deleting user photo', ['user_id' => $userId]);

        try {
            $this->graphClient->api('DELETE', $endpoint);
            $this->logInfo('User photo deleted successfully', ['user_id' => $userId]);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to delete user photo: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's manager.
     */
    public function getUserManager(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/manager";

        $this->logInfo('Getting user manager', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user manager: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get user's direct reports.
     */
    public function getUserDirectReports(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/directReports";

        $queryParams = $options['query'] ?? [];

        $this->logInfo('Getting user direct reports', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user direct reports: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's presence information.
     */
    public function getUserPresence(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/presence";

        $this->logInfo('Getting user presence', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user presence: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get multiple users by IDs.
     */
    public function getUsersByIds(array $userIds, array $options = []): GraphResponse
    {
        $ids = implode(',', $userIds);
        $endpoint = "/users?id={$ids}";

        $queryParams = array_merge([
            '$select' => $options['select'] ?? 'id,displayName,mail,userPrincipalName',
        ], $options['query'] ?? []);

        $this->logInfo('Getting users by IDs', ['user_ids' => $userIds]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get users by IDs: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get users from organization.
     */
    public function getOrganizationUsers(array $options = []): GraphResponse
    {
        $endpoint = '/users';

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 100,
            '$select' => $options['select'] ?? 'id,displayName,mail,userPrincipalName,jobTitle,department',
        ], $options['query'] ?? []);

        $this->logInfo('Getting organization users', ['limit' => $queryParams['$top']]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get organization users: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update user profile.
     */
    public function updateUser(string $userId, array $userData): GraphResponse
    {
        $endpoint = "/users/{$userId}";

        $this->logInfo('Updating user profile', ['user_id' => $userId, 'fields' => array_keys($userData)]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($userData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('User profile updated successfully', ['user_id' => $userId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update user profile: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update current user profile.
     */
    public function updateMyProfile(array $userData): GraphResponse
    {
        return $this->updateUser('me', $userData);
    }

    /**
     * Get user's activities.
     */
    public function getUserActivities(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/activities";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user activities', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user activities: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's license details.
     */
    public function getUserLicenseDetails(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/licenseDetails";

        $this->logInfo('Getting user license details', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user license details: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's group memberships.
     */
    public function getUserGroups(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/memberOf";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 100,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user groups', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user groups: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's joined teams.
     */
    public function getUserJoinedTeams(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/joinedTeams";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 100,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user joined teams', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user joined teams: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's calendar events.
     */
    public function getUserEvents(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/events";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
            '$select' => $options['select'] ?? 'id,subject,start,end,organizer,attendees',
        ], $options['query'] ?? []);

        $this->logInfo('Getting user events', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user events: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's messages (emails).
     */
    public function getUserMessages(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/messages";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
            '$select' => $options['select'] ?? 'id,subject,from,to,createdDateTime',
        ], $options['query'] ?? []);

        $this->logInfo('Getting user messages', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user messages: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's contacts.
     */
    public function getUserContacts(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/contacts";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 100,
            '$select' => $options['select'] ?? 'id,displayName,emailAddresses',
        ], $options['query'] ?? []);

        $this->logInfo('Getting user contacts', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user contacts: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Check if user exists.
     */
    public function userExists(string $userId): bool
    {
        try {
            $this->getUser($userId);
            return true;
        } catch (ServiceException $e) {
            if ($e->isItemNotFound()) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get user's OneDrive information.
     */
    public function getUserDrive(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/drive";

        $this->logInfo('Getting user drive', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user drive: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's default calendar.
     */
    public function getUserCalendar(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/calendar";

        $this->logInfo('Getting user calendar', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user calendar: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's mailbox settings.
     */
    public function getUserMailboxSettings(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/mailboxSettings";

        $this->logInfo('Getting user mailbox settings', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user mailbox settings: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update user's mailbox settings.
     */
    public function updateUserMailboxSettings(string $userId, array $settings): GraphResponse
    {
        $endpoint = "/users/{$userId}/mailboxSettings";

        $this->logInfo('Updating user mailbox settings', ['user_id' => $userId]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($settings),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('User mailbox settings updated successfully', ['user_id' => $userId]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update user mailbox settings: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's authentication methods.
     */
    public function getUserAuthenticationMethods(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/authentication/methods";

        $this->logInfo('Getting user authentication methods', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user authentication methods: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's sign-in activity.
     */
    public function getUserSignInActivity(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/authentication/signInActivity";

        $this->logInfo('Getting user sign-in activity', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user sign-in activity: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's devices.
     */
    public function getUserDevices(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/ownedDevices";

        $this->logInfo('Getting user devices', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user devices: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's app role assignments.
     */
    public function getUserAppRoles(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/appRoleAssignments";

        $this->logInfo('Getting user app roles', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user app roles: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's transitive member of groups.
     */
    public function getUserTransitiveMemberOf(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/transitiveMemberOf";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 100,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user transitive member of', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user transitive member of: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's oAuth2 permission grants.
     */
    public function getUserOAuth2PermissionGrants(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/oauth2PermissionGrants";

        $this->logInfo('Getting user OAuth2 permission grants', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user OAuth2 permission grants: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's settings.
     */
    public function getUserSettings(string $userId): GraphResponse
    {
        $endpoint = "/users/{$userId}/settings";

        $this->logInfo('Getting user settings', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user settings: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's profile insights.
     */
    public function getUserInsights(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/insights";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 50,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user insights', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user insights: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's trending documents.
     */
    public function getUserTrendingDocuments(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/insights/trending";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 20,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user trending documents', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user trending documents: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's used insights.
     */
    public function getUserUsedInsights(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/insights/used";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 20,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user used insights', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user used insights: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get user's shared insights.
     */
    public function getUserSharedInsights(string $userId, array $options = []): GraphResponse
    {
        $endpoint = "/users/{$userId}/insights/shared";

        $queryParams = array_merge([
            '$top' => $options['limit'] ?? 20,
        ], $options['query'] ?? []);

        $this->logInfo('Getting user shared insights', ['user_id' => $userId]);

        try {
            return $this->graphClient->api('GET', $endpoint, ['query' => $queryParams]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get user shared insights: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }
}
