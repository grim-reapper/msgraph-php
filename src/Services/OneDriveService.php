<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Services;

use GrimReapper\MsGraph\Core\GraphClientInterface;
use GrimReapper\MsGraph\Core\GraphResponseInterface;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Traits\HasLogging;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Service for Microsoft OneDrive operations.
 */
class OneDriveService
{
    use HasLogging;

    private GraphClientInterface $graphClient;
    private string $baseUrl = '/me/drive';

    public function __construct(GraphClientInterface $graphClient, LoggerInterface $logger)
    {
        $this->graphClient = $graphClient;
        $this->setLogger($logger);
    }

    /**
     * Set the base URL for OneDrive operations (me/drive, users/{id}/drive, etc.).
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get the base URL for OneDrive operations.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * List files and folders in a directory.
     */
    public function listFiles(?string $path = '/', array $options = []): GraphResponseInterface
    {
        if ($path === null) {
            throw new \InvalidArgumentException('Path cannot be null');
        }
        if ($path === '') {
            throw new \InvalidArgumentException('Path cannot be empty');
        }

        $endpoint = $this->buildPath($this->buildItemPath($path) . ':/children');

        $query = [];
        if (isset($options['limit'])) {
            $query['$top'] = $options['limit'];
        }
        if (isset($options['skip'])) {
            $query['$skip'] = $options['skip'];
        }
        if (isset($options['orderby'])) {
            $query['$orderby'] = $options['orderby'];
        }
        if (isset($options['filter'])) {
            $query['$filter'] = $options['filter'];
        }

        $this->logInfo('Listing files', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint, !empty($query) ? ['query' => $query] : []);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to list files: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get information about a specific file or folder.
     */
    public function getItem(string $path, array $options = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath($this->buildItemPath($path) . ':');

        $query = [];
        if (isset($options['expand'])) {
            $query['$expand'] = $options['expand'];
        }
        if (isset($options['select'])) {
            $query['$select'] = $options['select'];
        }

        $this->logInfo('Getting item info', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint, !empty($query) ? ['query' => $query] : []);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Upload a file to OneDrive.
     */
    public function uploadFile(string $localPath, string $remotePath, array $options = []): GraphResponseInterface
    {
        if (!file_exists($localPath)) {
            throw new ServiceException(
                'Local file does not exist: ' . $localPath,
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        $fileSize = filesize($localPath);
        $endpoint = $this->buildPath($this->buildItemPath($remotePath) . ':/content');

        $query = [];
        if (isset($options['conflictBehavior'])) {
            $query['@microsoft.graph.conflictBehavior'] = $options['conflictBehavior'];
        }

        $this->logInfo('Uploading file', [
            'local_path' => $localPath,
            'remote_path' => $remotePath,
            'size' => $fileSize,
        ]);

        try {
            $response = $this->graphClient->api('PUT', $endpoint, [
                'body' => fopen($localPath, 'rb'),
                'headers' => [
                    'Content-Type' => mime_content_type($localPath) ?: 'application/octet-stream',
                ],
                'query' => $query,
            ]);

            $this->logInfo('File uploaded successfully', [
                'remote_path' => $remotePath,
                'size' => $fileSize,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to upload file: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Download a file from OneDrive.
     */
    public function downloadFile(string $path, array $options = []): string
    {
        $endpoint = $this->buildPath($this->buildItemPath($path) . ':/content');

        $this->logInfo('Downloading file', ['path' => $path]);

        try {
            $response = $this->graphClient->api('GET', $endpoint);

            $this->logInfo('File downloaded successfully', ['path' => $path]);

            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to download file: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Create a new folder.
     */
    public function createFolder(string $path, string $name, array $options = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath($this->buildItemPath($path) . ':/children');

        $folderData = [
            'name' => $name,
            'folder' => new \stdClass(),
        ];

        if (isset($options['conflictBehavior'])) {
            $folderData['@microsoft.graph.conflictBehavior'] = $options['conflictBehavior'];
        }

        $this->logInfo('Creating folder', ['path' => $path, 'name' => $name]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($folderData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Folder created successfully', ['path' => $path, 'name' => $name]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create folder: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Delete a file or folder.
     */
    public function deleteItem(string $path): bool
    {
        $endpoint = $this->buildPath($this->buildItemPath($path) . ':');

        $this->logInfo('Deleting item', ['path' => $path]);

        try {
            $this->graphClient->api('DELETE', $endpoint);
            $this->logInfo('Item deleted successfully', ['path' => $path]);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to delete item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Copy a file or folder.
     */
    public function copyItem(string $sourcePath, string $destinationPath): GraphResponseInterface
    {
        if ($sourcePath === $destinationPath) {
            throw new \InvalidArgumentException('Source and destination paths cannot be the same');
        }

        $endpoint = $this->buildPath($this->buildItemPath($sourcePath) . ':/copy');
        $copyData = [
            'parentReference' => [
                'path' => '/drive/' . $this->buildItemPath(dirname($destinationPath)),
            ],
            'name' => basename($destinationPath),
        ];

        $this->logInfo('Copying item', [
            'source' => $sourcePath,
            'destination' => $destinationPath,
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($copyData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Item copied successfully', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to copy item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Move a file or folder.
     */
    public function moveItem(string $sourcePath, string $destinationPath): GraphResponseInterface
    {
        if ($sourcePath === $destinationPath) {
            throw new \InvalidArgumentException('Source and destination paths cannot be the same');
        }

        // Create new parent reference
        $newParentReference = [
            'path' => '/drive/' . $this->buildItemPath(dirname($destinationPath)),
        ];

        $updateData = [
            'parentReference' => $newParentReference,
            'name' => basename($destinationPath),
        ];

        $this->logInfo('Moving item', [
            'source' => $sourcePath,
            'destination' => $destinationPath,
        ]);

        try {
            $response = $this->updateItem($sourcePath, $updateData);

            $this->logInfo('Item moved successfully', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
            ]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to move item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update item metadata.
     */
    public function updateItem(string $path, array $updates): GraphResponseInterface
    {
        $endpoint = $this->buildPath($this->buildItemPath($path) . ':');

        $this->logInfo('Updating item', ['path' => $path, 'updates' => array_keys($updates)]);

        try {
            $response = $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode($updates),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Item updated successfully', ['path' => $path]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Search for files and folders.
     */
    public function search(string $query, string $path = '/', array $options = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath($this->buildItemPath($path) . ':/search(q=\'' . urlencode($query) . '\')');

        $queryParams = [];
        if (isset($options['limit'])) {
            $queryParams['$top'] = $options['limit'];
        }
        if (isset($options['orderby'])) {
            $queryParams['$orderby'] = $options['orderby'];
        }

        $this->logInfo('Searching files', ['query' => $query, 'path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint, !empty($queryParams) ? ['query' => $queryParams] : []);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to search files: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get recent files.
     */
    public function getRecentFiles(int $limit = 20): GraphResponseInterface
    {
        $endpoint = '/me/drive/recent';

        $this->logInfo('Getting recent files', ['limit' => $limit]);

        try {
            return $this->graphClient->api('GET', $endpoint, [
                'query' => ['$top' => $limit],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get recent files: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get shared files.
     */
    public function getSharedFiles(): GraphResponseInterface
    {
        $endpoint = '/me/drive/sharedWithMe';

        $this->logInfo('Getting shared files');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get shared files: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Share a file or folder.
     */
    public function shareItem(string $path, array $recipients, string $message = '', array $options = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/invite');
        $shareData = array_merge([
            'requireSignIn' => true,
            'sendInvitation' => true,
        ], $options);

        if (!empty($recipients)) {
            $shareData['recipients'] = array_map(fn ($email) => ['email' => $email], $recipients);
        }

        if (!empty($message)) {
            $shareData['message'] = $message;
        }

        $this->logInfo('Sharing item', [
            'path' => $path,
            'recipients' => count($recipients),
        ]);

        try {
            $response = $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($shareData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $this->logInfo('Item shared successfully', ['path' => $path]);

            return $response;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to share item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get sharing link for an item.
     */
    public function getSharingLink(string $path, string $type = 'view', array $options = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/createLink');
        $linkData = array_merge([
            'type' => $type,
            'scope' => 'anonymous',
        ], $options);

        $this->logInfo('Creating sharing link', ['path' => $path, 'type' => $type]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($linkData),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create sharing link: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get permissions for an item.
     */
    public function getPermissions(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/permissions');

        $this->logInfo('Getting permissions', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get permissions: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get thumbnails for an item.
     */
    public function getThumbnails(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/thumbnails');

        $this->logInfo('Getting thumbnails', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get thumbnails: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::UNSUPPORTED_FORMAT
            );
        }
    }

    /**
     * Get versions for an item.
     */
    public function getVersions(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/versions');

        $this->logInfo('Getting versions', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get versions: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ACCESS_DENIED
            );
        }
    }

    /**
     * Restore a version of an item.
     */
    public function restoreVersion(string $path, string $versionId): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/versions/' . $versionId . '/restoreVersion');

        $this->logInfo('Restoring version', ['path' => $path, 'version_id' => $versionId]);

        try {
            return $this->graphClient->api('POST', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to restore version: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get drive information.
     */
    public function getDriveInfo(): GraphResponseInterface
    {
        $endpoint = $this->buildPath('');

        $this->logInfo('Getting drive info');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get drive info: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INTERNAL_ERROR
            );
        }
    }

    /**
     * Get quota information.
     */
    public function getQuota(): GraphResponseInterface
    {
        $endpoint = $this->buildPath('quota');

        $this->logInfo('Getting quota info');

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get quota: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ACCESS_DENIED
            );
        }
    }

    /**
     * Get drive special folder.
     */
    public function getDriveSpecialFolder(string $folderName): GraphResponseInterface
    {
        $validFolders = ['documents', 'pictures', 'music', 'videos', 'approot'];
        if (!in_array($folderName, $validFolders)) {
            throw new \InvalidArgumentException("Invalid special folder name: {$folderName}");
        }

        $endpoint = $this->buildPath('special/' . $folderName);

        $this->logInfo('Getting special folder', ['folder' => $folderName]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get special folder: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get drive changes (delta).
     */
    public function getDriveChanges(string $token = null): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root/delta');

        $query = [];
        if ($token !== null) {
            $query['token'] = $token;
        }

        $this->logInfo('Getting drive changes', ['token' => $token]);

        try {
            return $this->graphClient->api('GET', $endpoint, !empty($query) ? ['query' => $query] : []);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get drive changes: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INTERNAL_ERROR
            );
        }
    }

    /**
     * Get item activities.
     */
    public function getItemActivities(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/activities');

        $this->logInfo('Getting item activities', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get item activities: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ACCESS_DENIED
            );
        }
    }

    /**
     * Get item analytics.
     */
    public function getItemAnalytics(string $path, string $timeRange = 'lastSevenDays'): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/analytics');

        if ($timeRange !== 'lastSevenDays') {
            $endpoint = $this->buildPath('root:' . $path . ':/analytics/' . $timeRange);
        }

        $this->logInfo('Getting item analytics', ['path' => $path, 'time_range' => $timeRange]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get item analytics: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Preview item.
     */
    public function previewItem(string $path, array $options = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/preview');

        $this->logInfo('Previewing item', ['path' => $path]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($options),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to preview item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::UNSUPPORTED_FORMAT
            );
        }
    }

    /**
     * Check in item.
     */
    public function checkInItem(string $path, string $comment = ''): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/checkin');

        $data = [];
        if (!empty($comment)) {
            $data['comment'] = $comment;
        }

        $this->logInfo('Checking in item', ['path' => $path]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode($data),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to check in item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::CONFLICT
            );
        }
    }

    /**
     * Check out item.
     */
    public function checkOutItem(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/checkout');

        $this->logInfo('Checking out item', ['path' => $path]);

        try {
            return $this->graphClient->api('POST', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to check out item: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::CONFLICT
            );
        }
    }

    /**
     * Get workbook session.
     */
    public function getWorkbookSession(string $path, bool $persistChanges = false): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/createSession');

        $this->logInfo('Creating workbook session', ['path' => $path]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode(['persistChanges' => $persistChanges]),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to create workbook session: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ACCESS_DENIED
            );
        }
    }

    /**
     * Close workbook session.
     */
    public function closeWorkbookSession(string $path, string $sessionId): bool
    {
        $endpoint = $this->buildPath('root:' . $path . ':/closeSession');

        $this->logInfo('Closing workbook session', ['path' => $path, 'session_id' => $sessionId]);

        try {
            $this->graphClient->api('POST', $endpoint);
            return true;
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to close workbook session: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get workbook worksheets.
     */
    public function getWorkbookWorksheets(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/worksheets');

        $this->logInfo('Getting workbook worksheets', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook worksheets: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get workbook worksheet.
     */
    public function getWorkbookWorksheet(string $path, string $worksheetName): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/worksheets/' . $worksheetName);

        $this->logInfo('Getting workbook worksheet', ['path' => $path, 'worksheet' => $worksheetName]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook worksheet: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get workbook worksheet range.
     */
    public function getWorkbookWorksheetRange(string $path, string $worksheetName, string $address): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/worksheets/' . $worksheetName . '/range(address=\'' . $address . '\')');

        $this->logInfo('Getting workbook worksheet range', ['path' => $path, 'worksheet' => $worksheetName, 'address' => $address]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook worksheet range: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Update workbook worksheet range.
     */
    public function updateWorkbookWorksheetRange(string $path, string $worksheetName, string $address, array $values): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/worksheets/' . $worksheetName . '/range(address=\'' . $address . '\')');

        $this->logInfo('Updating workbook worksheet range', ['path' => $path, 'worksheet' => $worksheetName, 'address' => $address]);

        try {
            return $this->graphClient->api('PATCH', $endpoint, [
                'body' => json_encode(['values' => $values]),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to update workbook worksheet range: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get workbook tables.
     */
    public function getWorkbookTables(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/tables');

        $this->logInfo('Getting workbook tables', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook tables: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get workbook named items.
     */
    public function getWorkbookNamedItems(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/names');

        $this->logInfo('Getting workbook named items', ['path' => $path]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook named items: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Execute workbook function.
     */
    public function executeWorkbookFunction(string $path, string $functionName, array $parameters = []): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/functions/' . $functionName);

        $this->logInfo('Executing workbook function', ['path' => $path, 'function' => $functionName]);

        try {
            return $this->graphClient->api('POST', $endpoint, [
                'body' => json_encode(['values' => $parameters]),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to execute workbook function: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Get workbook charts.
     */
    public function getWorkbookCharts(string $path, string $worksheetName): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/worksheets/' . $worksheetName . '/charts');

        $this->logInfo('Getting workbook charts', ['path' => $path, 'worksheet' => $worksheetName]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook charts: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get workbook pivot tables.
     */
    public function getWorkbookPivotTables(string $path, string $worksheetName): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/worksheets/' . $worksheetName . '/pivotTables');

        $this->logInfo('Getting workbook pivot tables', ['path' => $path, 'worksheet' => $worksheetName]);

        try {
            return $this->graphClient->api('GET', $endpoint);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get workbook pivot tables: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Get item content stream.
     */
    public function getItemContentStream(string $path): mixed
    {
        $endpoint = $this->buildPath('root:' . $path . ':/content');

        $this->logInfo('Getting item content stream', ['path' => $path]);

        try {
            $response = $this->graphClient->api('GET', $endpoint, [
                'stream' => true,
            ]);
            return $response->getBody();
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to get item content stream: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::ITEM_NOT_FOUND
            );
        }
    }

    /**
     * Check if a path exists.
     */
    public function itemExists(string $path): bool
    {
        try {
            $this->getItem($path);
            return true;
        } catch (ServiceException $e) {
            if ($e->isItemNotFound()) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get file content as stream.
     */
    public function getFileStream(string $path): mixed
    {
        return $this->getItemContentStream($path);
    }

    /**
     * Upload large file in chunks.
     */
    public function uploadLargeFile(string $localPath, string $remotePath, int $chunkSize = 3276800): GraphResponseInterface
    {
        if (!file_exists($localPath)) {
            throw new ServiceException(
                'Local file does not exist: ' . $localPath,
                0,
                400,
                ServiceException::INVALID_REQUEST
            );
        }

        $fileSize = filesize($localPath);

        if ($fileSize <= $chunkSize) {
            return $this->uploadFile($localPath, $remotePath);
        }

        $this->logInfo('Uploading large file in chunks', [
            'local_path' => $localPath,
            'remote_path' => $remotePath,
            'size' => $fileSize,
            'chunk_size' => $chunkSize,
        ]);

        // Create upload session
        $session = $this->createUploadSession($remotePath);

        try {
            $sessionUrl = $session->get('uploadUrl');

            $handle = fopen($localPath, 'rb');
            $chunkNumber = 0;
            $bytesUploaded = 0;

            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                $chunkLength = strlen($chunk);
                $bytesUploaded += $chunkLength;

                $startRange = $chunkNumber * $chunkSize;
                $endRange = $startRange + $chunkLength - 1;

                $this->uploadChunk($sessionUrl, $chunk, $startRange, $endRange, $fileSize);

                $chunkNumber++;
                $this->logDebug('Uploaded chunk', [
                    'chunk' => $chunkNumber,
                    'bytes' => $bytesUploaded,
                    'total' => $fileSize,
                ]);
            }

            fclose($handle);

            $this->logInfo('Large file uploaded successfully', [
                'remote_path' => $remotePath,
                'size' => $fileSize,
            ]);

            return $this->getItem($remotePath);
        } catch (GuzzleException $e) {
            throw new ServiceException(
                'Failed to upload large file: ' . $e->getMessage(),
                $e->getCode(),
                0,
                ServiceException::INVALID_REQUEST
            );
        }
    }

    /**
     * Create upload session for large files.
     */
    private function createUploadSession(string $path): GraphResponseInterface
    {
        $endpoint = $this->buildPath('root:' . $path . ':/createUploadSession');
        $sessionData = [
            'item' => [
                '@microsoft.graph.conflictBehavior' => 'rename',
            ],
        ];

        return $this->graphClient->api('POST', $endpoint, [
            'body' => json_encode($sessionData),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    /**
     * Upload a chunk of a large file.
     */
    private function uploadChunk(string $sessionUrl, string $chunk, int $startRange, int $endRange, int $totalSize): void
    {
        $contentRange = "bytes {$startRange}-{$endRange}/{$totalSize}";

        $this->graphClient->getHttpClient()->request('PUT', $sessionUrl, [
            'body' => $chunk,
            'headers' => [
                'Content-Length' => strlen($chunk),
                'Content-Range' => $contentRange,
            ],
        ]);
    }

    /**
     * Build the full endpoint path.
     */
    private function buildPath(string $path): string
    {
        if (empty($path)) {
            return $this->baseUrl;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Convert service to string representation.
     */
    public function __toString(): string
    {
        return 'OneDriveService(baseUrl: ' . $this->baseUrl . ')';
    }

    /**
     * Build the item path for the API endpoint.
     */
    private function buildItemPath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return 'root';
        }
        // The path should not have a leading slash for the API, and should be encoded.
        $trimmedPath = ltrim($path, '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $trimmedPath)));
        return 'root:/' . $encodedPath;
    }
}
