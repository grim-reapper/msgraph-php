<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Services\OneDriveService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * OneDrive Operations Examples.
 *
 * This file demonstrates various OneDrive operations using the Microsoft Graph PHP Package.
 */

// Setup logging
$logger = new Logger('msgraph-onedrive');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Initialize configuration
$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback',
    'scopes' => [
        'https://graph.microsoft.com/User.Read',
        'https://graph.microsoft.com/Files.ReadWrite.All',
        'https://graph.microsoft.com/Sites.ReadWrite.All',
    ],
    'accessToken' => 'your-access-token', // In production, get this from OAuth flow
]);

// Initialize cache
$cache = new FilesystemAdapter('msgraph', 3600, __DIR__ . '/../../cache');

// Initialize Graph client
$graphClient = new GraphClient($config, null, $logger, $cache);

// Initialize OneDrive service
$oneDrive = new OneDriveService($graphClient, $logger);

echo "=== OneDrive Operations Examples ===\n\n";

try {
    // Example 1: List files and folders
    echo "1. List Files in Root Directory:\n";
    echo "--------------------------------\n";

    $rootFiles = $oneDrive->listFiles('/');
    $files = $rootFiles->get('value', []);

    echo 'Found ' . count($files) . " items in root directory:\n";
    foreach ($files as $file) {
        $type = $file['folder'] ?? false ? 'Folder' : 'File';
        $name = $file['name'] ?? 'Unknown';
        $size = $file['size'] ?? 0;
        echo "  - {$type}: {$name} ({$size} bytes)\n";
    }
    echo "\n";

    // Example 2: Get drive information
    echo "2. Drive Information:\n";
    echo "---------------------\n";

    $driveInfo = $oneDrive->getDriveInfo();
    $driveName = $driveInfo->get('name', 'N/A');
    $driveType = $driveInfo->get('driveType', 'N/A');

    echo "Drive Name: {$driveName}\n";
    echo "Drive Type: {$driveType}\n";

    // Example 3: Get quota information
    echo "\n3. Storage Quota:\n";
    echo "----------------\n";

    $quota = $oneDrive->getQuota();
    $used = $quota->get('used', 0);
    $total = $quota->get('total', 0);
    $remaining = $total - $used;

    $usedGB = round($used / 1024 / 1024 / 1024, 2);
    $totalGB = round($total / 1024 / 1024 / 1024, 2);
    $remainingGB = round($remaining / 1024 / 1024 / 1024, 2);

    echo "Used: {$usedGB} GB\n";
    echo "Total: {$totalGB} GB\n";
    echo "Remaining: {$remainingGB} GB\n";
    echo 'Usage: ' . round(($used / $total) * 100, 2) . "%\n\n";

    // Example 4: Create a folder
    echo "4. Create Folder:\n";
    echo "----------------\n";

    $folderName = 'Test Folder ' . date('Y-m-d H:i:s');
    $newFolder = $oneDrive->createFolder('/', $folderName);

    if ($newFolder->isSuccess()) {
        $folderId = $newFolder->get('id');
        echo "Created folder: {$folderName} (ID: {$folderId})\n\n";
    } else {
        echo "Failed to create folder\n\n";
    }

    // Example 5: Upload a file
    echo "5. Upload File:\n";
    echo "--------------\n";

    $localFile = __DIR__ . '/sample-upload.txt';
    $remotePath = '/' . $folderName . '/sample.txt';

    // Create a sample file for upload
    $sampleContent = "This is a sample file for upload demonstration.\nCreated: " . date('Y-m-d H:i:s');
    file_put_contents($localFile, $sampleContent);

    if (file_exists($localFile)) {
        $uploadResult = $oneDrive->uploadFile($localFile, $remotePath);

        if ($uploadResult->isSuccess()) {
            $fileId = $uploadResult->get('id');
            echo "File uploaded successfully (ID: {$fileId})\n\n";
        } else {
            echo "Failed to upload file\n\n";
        }

        // Clean up local file
        unlink($localFile);
    } else {
        echo "Sample file not found, skipping upload\n\n";
    }

    // Example 6: Search files
    echo "6. Search Files:\n";
    echo "---------------\n";

    $searchResults = $oneDrive->search('sample');
    $searchFiles = $searchResults->get('value', []);

    echo 'Found ' . count($searchFiles) . " files matching 'sample':\n";
    foreach ($searchFiles as $file) {
        $name = $file['name'] ?? 'Unknown';
        $path = $file['parentReference']['path'] ?? 'Unknown';
        echo "  - {$name} (Path: {$path})\n";
    }
    echo "\n";

    // Example 7: Get recent files
    echo "7. Recent Files:\n";
    echo "---------------\n";

    $recentFiles = $oneDrive->getRecentFiles(5);
    $recent = $recentFiles->get('value', []);

    echo "Recent files:\n";
    foreach ($recent as $file) {
        $name = $file['name'] ?? 'Unknown';
        $lastModified = $file['lastModifiedDateTime'] ?? 'Unknown';
        echo "  - {$name} (Modified: {$lastModified})\n";
    }
    echo "\n";

    // Example 8: File operations (copy, move, delete)
    echo "8. File Operations:\n";
    echo "------------------\n";

    // Copy file
    if (isset($fileId)) {
        $copyPath = '/' . $folderName . '/sample-copy.txt';
        $copyResult = $oneDrive->copyItem($remotePath, $copyPath);

        if ($copyResult->isSuccess()) {
            $copiedFileId = $copyResult->get('id');
            echo "File copied successfully (ID: {$copiedFileId})\n";
        }

        // Delete the copy
        $oneDrive->deleteItem($copyPath);
        echo "Copied file deleted\n";
    }

    // Example 9: Share file
    echo "\n9. Share File:\n";
    echo "-------------\n";

    if (isset($fileId)) {
        $shareResult = $oneDrive->getSharingLink($remotePath, 'view');

        if ($shareResult->isSuccess()) {
            $shareLink = $shareResult->get('link', []);
            $webUrl = $shareLink['webUrl'] ?? 'N/A';
            echo "Sharing link: {$webUrl}\n";
        } else {
            echo "Failed to create sharing link\n";
        }
    }

    // Example 10: Download file
    echo "\n10. Download File:\n";
    echo "-----------------\n";

    if (isset($fileId)) {
        try {
            $downloadedContent = $oneDrive->downloadFile($remotePath);
            echo 'Downloaded file content (' . strlen($downloadedContent) . " bytes):\n";
            echo substr($downloadedContent, 0, 100) . "...\n";
        } catch (Exception $e) {
            echo 'Failed to download file: ' . $e->getMessage() . "\n";
        }
    }

    // Cleanup: Delete test folder
    echo "\n11. Cleanup:\n";
    echo "-----------\n";

    try {
        $oneDrive->deleteItem('/' . $folderName);
        echo "Test folder deleted successfully\n";
    } catch (Exception $e) {
        echo 'Failed to delete test folder: ' . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== OneDrive Examples Completed ===\n";

/**
 * Helper Functions.
 */

/**
 * Example: Upload large file with progress tracking.
 */
function uploadLargeFileExample(OneDriveService $oneDrive, string $localPath, string $remotePath): void
{
    echo "Uploading large file with progress tracking...\n";

    try {
        $oneDrive->uploadLargeFile($localPath, $remotePath, 3276800); // 3.27MB chunks
        echo "Large file uploaded successfully\n";
    } catch (Exception $e) {
        echo 'Large file upload failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Batch operations.
 */
function batchOperationsExample(GraphClient $graphClient): void
{
    echo "Performing batch operations...\n";

    $requests = [
        'drive_info' => [
            'method' => 'GET',
            'endpoint' => '/me/drive',
        ],
        'recent_files' => [
            'method' => 'GET',
            'endpoint' => '/me/drive/recent',
            'query' => ['$top' => 3],
        ],
        'quota' => [
            'method' => 'GET',
            'endpoint' => '/me/drive/quota',
        ],
    ];

    try {
        $responses = $graphClient->batchRequest($requests);

        echo "Batch request completed:\n";
        foreach ($responses as $id => $response) {
            $status = $response['status'] ?? 'unknown';
            echo "  - {$id}: HTTP {$status}\n";
        }
    } catch (Exception $e) {
        echo 'Batch request failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Error handling.
 */
function errorHandlingExample(OneDriveService $oneDrive): void
{
    echo "Demonstrating error handling...\n";

    try {
        // Try to access non-existent file
        $oneDrive->getItem('/non-existent-file.txt');
    } catch (Exception $e) {
        echo 'Caught expected error: ' . $e->getMessage() . "\n";

        // Check error type
        if ($e instanceof \GrimReapper\MsGraph\Exceptions\ServiceException) {
            if ($e->isItemNotFound()) {
                echo "File not found - this is expected\n";
            }
        }
    }
}

/**
 * Example: Working with different drives.
 */
function differentDrivesExample(OneDriveService $oneDrive): void
{
    echo "Working with different OneDrive configurations...\n";

    // Personal OneDrive (default)
    $oneDrive->setBaseUrl('/me/drive');
    echo "Switched to personal OneDrive\n";

    // Business OneDrive
    $oneDrive->setBaseUrl('/me/drive');
    echo "Using business OneDrive\n";

    // Specific user's drive (if you have permissions)
    // $oneDrive->setBaseUrl('/users/user-id/drive');
    // echo "Switched to user's OneDrive\n";
}

/**
 * Example: File type operations.
 */
function fileTypeOperationsExample(OneDriveService $oneDrive): void
{
    echo "File type specific operations...\n";

    // List only folders
    $folders = $oneDrive->listFiles('/');
    $items = $folders->get('value', []);

    $folderCount = 0;
    $fileCount = 0;

    foreach ($items as $item) {
        if (!empty($item['folder'])) {
            $folderCount++;
        } else {
            $fileCount++;
        }
    }

    echo "Folders: {$folderCount}, Files: {$fileCount}\n";
}

/**
 * Example: Metadata operations.
 */
function metadataOperationsExample(OneDriveService $oneDrive, string $filePath): void
{
    echo "Metadata operations...\n";

    try {
        // Get file metadata
        $fileInfo = $oneDrive->getItem($filePath);
        $metadata = $fileInfo->getBody();

        echo "File metadata:\n";
        echo '  Name: ' . ($metadata['name'] ?? 'N/A') . "\n";
        echo '  Size: ' . ($metadata['size'] ?? 'N/A') . " bytes\n";
        echo '  Created: ' . ($metadata['createdDateTime'] ?? 'N/A') . "\n";
        echo '  Modified: ' . ($metadata['lastModifiedDateTime'] ?? 'N/A') . "\n";

        // Update metadata
        $updated = $oneDrive->updateItem($filePath, [
            'description' => 'Updated via Microsoft Graph PHP Package',
        ]);

        if ($updated->isSuccess()) {
            echo "Metadata updated successfully\n";
        }
    } catch (Exception $e) {
        echo 'Metadata operation failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Advanced search with filters.
 */
function advancedSearchExample(OneDriveService $oneDrive): void
{
    echo "Advanced search operations...\n";

    try {
        // Search with filters
        $searchResults = $oneDrive->search('document', '/Documents');

        $files = $searchResults->get('value', []);
        echo 'Found ' . count($files) . " documents in /Documents folder\n";

        // You can also use OData filters for more complex queries
        // This would require extending the OneDriveService or using GraphClient directly
    } catch (Exception $e) {
        echo 'Advanced search failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Permission management.
 */
function permissionManagementExample(OneDriveService $oneDrive, string $filePath): void
{
    echo "Permission management...\n";

    try {
        // Share with specific users
        $shareResult = $oneDrive->shareItem($filePath, ['user@example.com'], 'Please review this document');

        if ($shareResult->isSuccess()) {
            $permissions = $shareResult->get('value', []);
            echo 'Shared with ' . count($permissions) . " users\n";
        }

        // Get sharing links
        $viewLink = $oneDrive->getSharingLink($filePath, 'view');
        $editLink = $oneDrive->getSharingLink($filePath, 'edit');

        if ($viewLink->isSuccess()) {
            $link = $viewLink->get('link', []);
            echo 'View link: ' . ($link['webUrl'] ?? 'N/A') . "\n";
        }
    } catch (Exception $e) {
        echo 'Permission management failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Working with shared files.
 */
function sharedFilesExample(OneDriveService $oneDrive): void
{
    echo "Working with shared files...\n";

    try {
        $sharedFiles = $oneDrive->getSharedFiles();
        $shared = $sharedFiles->get('value', []);

        echo 'Shared files: ' . count($shared) . "\n";

        foreach ($shared as $file) {
            $name = $file['name'] ?? 'Unknown';
            $sharedBy = $file['sharedBy'] ?? 'Unknown';
            echo "  - {$name} (shared by: {$sharedBy})\n";
        }
    } catch (Exception $e) {
        echo 'Shared files operation failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Stream operations for large files.
 */
function streamOperationsExample(OneDriveService $oneDrive, string $filePath): void
{
    echo "Stream operations for large files...\n";

    try {
        // Get file as stream (for large files)
        $stream = $oneDrive->getFileStream($filePath);

        if ($stream) {
            echo "Stream opened successfully\n";
            // Process stream...
            // $stream->close();
        }
    } catch (Exception $e) {
        echo 'Stream operation failed: ' . $e->getMessage() . "\n";
    }
}

/**
 * Example: Complete workflow.
 */
function completeWorkflowExample(OneDriveService $oneDrive, GraphClient $graphClient): void
{
    echo "Complete workflow example...\n";

    try {
        // 1. Create a project folder
        $projectName = 'Project-' . date('Y-m-d');
        $oneDrive->createFolder('/', $projectName);

        // 2. Upload project files
        $files = ['requirements.txt', 'design.docx', 'budget.xlsx'];
        foreach ($files as $file) {
            $localPath = __DIR__ . '/' . $file;
            $remotePath = '/' . $projectName . '/' . $file;

            if (file_exists($localPath)) {
                $oneDrive->uploadFile($localPath, $remotePath);
                echo "Uploaded: {$file}\n";
            }
        }

        // 3. Share the project folder
        $oneDrive->getSharingLink('/' . $projectName, 'edit');

        // 4. Get project status
        $projectFiles = $oneDrive->listFiles('/' . $projectName);
        $fileCount = count($projectFiles->get('value', []));

        echo "Project created with {$fileCount} files\n";

        // 5. Clean up (in real scenario, you might not want to delete)
        // $oneDrive->deleteItem('/' . $projectName);
    } catch (Exception $e) {
        echo 'Workflow failed: ' . $e->getMessage() . "\n";
    }
}

// Additional examples (uncomment to run)
// batchOperationsExample($graphClient);
// errorHandlingExample($oneDrive);
// differentDrivesExample($oneDrive);
// fileTypeOperationsExample($oneDrive);
// metadataOperationsExample($oneDrive, '/sample.txt');
// advancedSearchExample($oneDrive);
// permissionManagementExample($oneDrive, '/sample.txt');
// sharedFilesExample($oneDrive);
// streamOperationsExample($oneDrive, '/large-file.zip');
// completeWorkflowExample($oneDrive, $graphClient);

echo "\n=== Additional Examples Available ===\n";
echo "Uncomment the function calls above to run more examples:\n";
echo "- batchOperationsExample()\n";
echo "- errorHandlingExample()\n";
echo "- differentDrivesExample()\n";
echo "- fileTypeOperationsExample()\n";
echo "- metadataOperationsExample()\n";
echo "- advancedSearchExample()\n";
echo "- permissionManagementExample()\n";
echo "- sharedFilesExample()\n";
echo "- streamOperationsExample()\n";
echo "- completeWorkflowExample()\n";

echo "\n=== OneDrive Examples Documentation ===\n";
echo "For more information, see:\n";
echo "- API Documentation: docs/api/index.md\n";
echo "- Authentication Guide: docs/guides/authentication.md\n";
echo "- Best Practices: docs/guides/best-practices.md\n";
