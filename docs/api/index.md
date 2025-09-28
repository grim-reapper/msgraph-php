# API Documentation

## Overview

This API documentation provides comprehensive information about the Microsoft Graph PHP Package classes, methods, and their usage.

## Core Classes

### GraphClient

The main client for Microsoft Graph API operations.

```php
use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Authentication\AuthConfig;

$config = new AuthConfig([...]);
$graphClient = new GraphClient($config);
```

#### Methods

- `api(string $method, string $endpoint, array $options = []): GraphResponse`
- `createRequest(string $method, string $endpoint): GraphRequest`
- `execute(GraphRequest $request): GraphResponse`
- `createCollectionRequest(string $method, string $endpoint, string $collectionClass): GraphCollectionRequest`
- `batchRequest(array $requests): array`

### GraphResponse

Enhanced response wrapper for Microsoft Graph API responses.

```php
$response = $graphClient->api('GET', '/me');
$userName = $response->get('displayName');
$isSuccess = $response->isSuccess();
```

#### Methods

- `getBody(): mixed`
- `getStatusCode(): int`
- `isSuccess(): bool`
- `isError(): bool`
- `get(string $key, mixed $default = null): mixed`
- `toArray(): array`

## Authentication Classes

### AuthConfig

Configuration class for Microsoft Graph authentication.

```php
use GrimReapper\MsGraph\Authentication\AuthConfig;

$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback',
    'scopes' => ['https://graph.microsoft.com/User.Read'],
]);
```

#### Methods

- `getClientId(): string`
- `getTenantId(): string`
- `getAuthorizationUrl(array $params = []): string`
- `isAccessTokenExpired(): bool`

### TokenManager

Manages OAuth 2.0 tokens with secure storage and automatic refresh.

```php
use GrimReapper\MsGraph\Authentication\TokenManager;
use GrimReapper\MsGraph\Authentication\OAuth2Provider;

$provider = new OAuth2Provider($config->toArray());
$tokenManager = new TokenManager($provider, $config, $cache);

$accessToken = $tokenManager->getAccessToken();
```

#### Methods

- `getAccessToken(): string`
- `refreshAccessToken(?string $refreshToken = null): array`
- `exchangeAuthorizationCode(string $code): AccessToken`
- `isTokenExpired(?array $tokenData = null): bool`

## Service Classes

### OneDriveService

Service for Microsoft OneDrive operations.

```php
use GrimReapper\MsGraph\Services\OneDriveService;

$oneDrive = new OneDriveService($graphClient);

// List files
$files = $oneDrive->listFiles('/');

// Upload file
$oneDrive->uploadFile('/local/path/file.pdf', '/remote/path/file.pdf');

// Download file
$content = $oneDrive->downloadFile('/remote/path/file.pdf');
```

#### Key Methods

- `listFiles(string $path = '/'): GraphResponse`
- `getItem(string $path): GraphResponse`
- `uploadFile(string $localPath, string $remotePath): GraphResponse`
- `uploadLargeFile(string $localPath, string $remotePath): GraphResponse`
- `downloadFile(string $path): string`
- `createFolder(string $path, string $name): GraphResponse`
- `copyItem(string $sourcePath, string $destinationPath): GraphResponse`
- `moveItem(string $sourcePath, string $destinationPath): GraphResponse`
- `updateItem(string $path, array $updates): GraphResponse`
- `deleteItem(string $path): bool`
- `search(string $query, string $path = '/') : GraphResponse`
- `getRecentFiles(int $limit = 20): GraphResponse`
- `getSharedFiles(): GraphResponse`
- `shareItem(string $path, array $recipients): GraphResponse`
- `getSharingLink(string $path, string $type = 'view'): GraphResponse`
- `itemExists(string $path): bool`
- `getDriveInfo(): GraphResponse`
- `getQuota(): GraphResponse`
- `getDriveChanges(string $token = null): GraphResponse`
- `getItemActivities(string $path): GraphResponse`
- `getItemAnalytics(string $path, string $timeRange = 'lastSevenDays'): GraphResponse`

### DocumentService

Service for document conversion operations.

```php
use GrimReapper\MsGraph\Services\DocumentService;

$documentService = new DocumentService($graphClient);

// Convert DOCX to PDF
$pdfContent = $documentService->convertDocxToPdf('/document.docx');

// Batch conversion
$results = $documentService->batchConvert([
    ['input_path' => '/doc1.docx', 'output_path' => '/pdf1.pdf'],
    ['input_path' => '/doc2.docx', 'output_path' => '/pdf2.pdf'],
], 'pdf');
```

#### Key Methods

- `convertDocument(string $inputPath, string $outputFormat): string`
- `convertDocxToPdf(string $inputPath): string`
- `batchConvert(array $documents, string $outputFormat): array`
- `getSupportedFormats(): array`
- `canConvert(string $inputPath, string $outputFormat): bool`

### UserService

Service for Microsoft Graph user operations.

```php
use GrimReapper\MsGraph\Services\UserService;

$userService = new UserService($graphClient);

// Get current user
$user = $userService->getCurrentUser();

// Search users
$users = $userService->searchUsers('john.doe');

// Get user photo
$photo = $userService->getUserPhoto('user-id');
```

#### Key Methods

- `getCurrentUser(): GraphResponse`
- `getUser(string $userId): GraphResponse`
- `searchUsers(string $query, array $options = []): GraphResponse`
- `getUserPhoto(string $userId, string $size = 'medium'): string`
- `updateUser(string $userId, array $userData): GraphResponse`

### MailService

Service for Microsoft Graph mail operations.

```php
use GrimReapper\MsGraph\Services\MailService;

$mailService = new MailService($graphClient);

// Send email
$mailService->sendEmail([
    'to' => ['recipient@example.com'],
    'subject' => 'Test Email',
    'body' => 'This is a test email',
    'attachments' => [...]
]);

// Get messages
$messages = $mailService->getMessages(['limit' => 10]);
```

#### Key Methods

- `sendEmail(array $emailData): GraphResponse`
- `getMessages(array $options = []): GraphResponse`
- `getMessage(string $messageId): GraphResponse`
- `createDraft(array $emailData): GraphResponse`
- `replyToMessage(string $messageId, string $comment = ''): GraphResponse`

### CalendarService

Service for Microsoft Graph calendar operations.

```php
use GrimReapper\MsGraph\Services\CalendarService;

$calendarService = new CalendarService($graphClient);

// Create event
$calendarService->createEvent([
    'subject' => 'Team Meeting',
    'start' => '2024-01-15T10:00:00',
    'end' => '2024-01-15T11:00:00',
    'attendees' => [
        ['email' => 'colleague@example.com', 'name' => 'Colleague Name']
    ]
]);

// Get today's events
$todayEvents = $calendarService->getTodaysEvents();
```

#### Key Methods

- `getEvents(array $options = []): GraphResponse`
- `createEvent(array $eventData): GraphResponse`
- `updateEvent(string $eventId, array $eventData): GraphResponse`
- `deleteEvent(string $eventId): bool`
- `acceptEvent(string $eventId, string $comment = ''): GraphResponse`

## Exception Classes

### GraphException

Base exception class for Microsoft Graph API errors.

```php
use GrimReapper\MsGraph\Exceptions\GraphException;

try {
    $response = $graphClient->api('GET', '/me');
} catch (GraphException $e) {
    echo "Error: " . $e->getMessage();
    echo "HTTP Status: " . $e->getHttpStatusCode();
    echo "Error Code: " . $e->getErrorCode();
}
```

### AuthenticationException

Exception thrown when authentication fails.

```php
use GrimReapper\MsGraph\Exceptions\AuthenticationException;

try {
    $token = $tokenManager->getAccessToken();
} catch (AuthenticationException $e) {
    if ($e->isTokenExpired()) {
        // Handle token refresh
    }
}
```

### ServiceException

Exception thrown when service operations fail.

```php
use GrimReapper\MsGraph\Exceptions\ServiceException;

try {
    $files = $oneDrive->listFiles('/');
} catch (ServiceException $e) {
    if ($e->isItemNotFound()) {
        // Handle not found
    }
}
```

## Traits

### HasLogging

Provides logging functionality to classes.

```php
use GrimReapper\MsGraph\Traits\HasLogging;

class MyService
{
    use HasLogging;

    public function doSomething(): void
    {
        $this->logInfo('Doing something important');
    }
}
```

### HasCache

Provides caching functionality to classes.

```php
use GrimReapper\MsGraph\Traits\HasCache;

class MyService
{
    use HasCache;

    public function getData(string $key): mixed
    {
        return $this->remember($key, function () {
            return $this->fetchExpensiveData();
        }, 3600); // Cache for 1 hour
    }
}
```

## Response Format

All service methods return `GraphResponse` objects with the following interface:

```php
interface GraphResponse
{
    public function getBody(): mixed;
    public function getStatusCode(): int;
    public function isSuccess(): bool;
    public function isError(): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function toArray(): array;
    public function getIterator(): Traversable;
}
```

## Error Handling

The package provides comprehensive error handling:

```php
try {
    $response = $service->someOperation();
} catch (AuthenticationException $e) {
    // Handle authentication errors
    $userFriendlyMessage = $e->getUserFriendlyMessage();
} catch (ServiceException $e) {
    // Handle service errors
    if ($e->isRetryable()) {
        // Retry the operation
    }
    $userFriendlyMessage = $e->getUserFriendlyMessage();
} catch (GraphException $e) {
    // Handle general Graph API errors
    $httpStatus = $e->getHttpStatusCode();
    $errorCode = $e->getErrorCode();
}
```

## Rate Limiting

The package handles rate limiting automatically:

```php
try {
    $response = $graphClient->api('GET', '/me');
} catch (ServiceException $e) {
    if ($e->isRateLimitExceeded()) {
        $retryAfter = $e->getRetryAfter();
        // Wait and retry
        sleep($retryAfter ?? 60);
    }
}
```

## Pagination

For paginated responses:

```php
$collectionRequest = $graphClient->createCollectionRequest('GET', '/me/events', 'Event');

// Get all results
$allEvents = $collectionRequest->getAll();

// Get first page
$firstPage = $collectionRequest->first(50);

// Get next page
$nextPage = $collectionRequest->next($previousResponse);
```

## Batch Requests

For multiple operations:

```php
$requests = [
    'user' => [
        'method' => 'GET',
        'endpoint' => '/me',
    ],
    'events' => [
        'method' => 'GET',
        'endpoint' => '/me/events',
        'query' => ['$top' => 5],
    ],
];

$responses = $graphClient->batchRequest($requests);
```

## Configuration Options

### Authentication Configuration

```php
$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback',
    'scopes' => [
        'https://graph.microsoft.com/User.Read',
        'https://graph.microsoft.com/Files.ReadWrite.All',
        'https://graph.microsoft.com/Mail.ReadWrite',
        'https://graph.microsoft.com/Calendars.ReadWrite',
    ],
    'timeout' => 30,
    'cache' => $cacheInstance, // Optional
    'logger' => $loggerInstance, // Optional
]);
```

### Service Configuration

```php
$oneDrive = new OneDriveService($graphClient, $logger);
$oneDrive->setBaseUrl('/me/drive'); // Default: '/me/drive'

$documentService = new DocumentService($graphClient, $logger);
$mailService = new MailService($graphClient, $logger);
$calendarService = new CalendarService($graphClient, $logger);
$userService = new UserService($graphClient, $logger);
```

## Best Practices

1. **Always handle exceptions** - Wrap API calls in try-catch blocks
2. **Use caching** - Inject cache for better performance
3. **Implement logging** - Use PSR-3 logger for debugging
4. **Validate inputs** - Check required parameters before API calls
5. **Handle rate limits** - Implement retry logic for rate-limited requests
6. **Secure token storage** - Use secure cache backends for production

## Troubleshooting

### Common Issues

1. **Authentication fails**
   - Check client ID, secret, and tenant ID
   - Verify redirect URI matches Azure AD configuration
   - Ensure access token is valid and not expired

2. **Permission denied**
   - Verify Azure AD app has required permissions
   - Check user has consented to permissions
   - Validate scopes in authentication request

3. **Rate limiting**
   - Implement exponential backoff
   - Use caching to reduce API calls
   - Batch requests when possible

4. **File upload fails**
   - Check file size limits
   - Verify file path and permissions
   - Use chunked upload for large files

### Debug Mode

Enable debug logging to troubleshoot issues:

```php
$logger = new Monolog\Logger('msgraph');
$logger->pushHandler(new Monolog\Handler\StreamHandler('php://stdout', Monolog\Logger::DEBUG));

$graphClient = new GraphClient($config, null, $logger, $cache);
```

## Support

For more information, examples, and support:

- [GitHub Repository](https://github.com/grim-reapper/msgraph-php)
- [Issues](https://github.com/grim-reapper/msgraph-php/issues)
- [Discussions](https://github.com/grim-reapper/msgraph-php/discussions)
- [Email Support](mailto:support@grim-reapper.com)
