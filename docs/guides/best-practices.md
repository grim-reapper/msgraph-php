# Best Practices Guide

## Overview

This guide provides best practices for using the Microsoft Graph PHP Package effectively, securely, and efficiently in production environments.

## 1. Security Best Practices

### Authentication Security

#### Use HTTPS Everywhere
```php
// Always use HTTPS for production
$config = new AuthConfig([
    'redirectUri' => 'https://yourapp.com/callback',
]);

// Set secure session cookies
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
```

#### Implement CSRF Protection
```php
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$authUrl = $config->getAuthorizationUrl(['state' => $state]);

// Verify state in callback
if ($_GET['state'] !== $_SESSION['oauth_state']) {
    throw new Exception('Invalid state parameter');
}
```

#### Use PKCE for Enhanced Security
```php
$codeVerifier = bin2hex(random_bytes(32));
$codeChallenge = base64url_encode(hash('sha256', $codeVerifier, true));

$authUrl = $config->getAuthorizationUrl([
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256',
]);
```

### Token Security

#### Secure Token Storage
```php
use Symfony\Component\Cache\Adapter\RedisAdapter;

// Use Redis for production
$redis = new Redis();
$redis->connect('localhost', 6379);
$cache = new RedisAdapter($redis);

// Encrypt sensitive cache data
$cache = new EncryptedAdapter($cache, $encryptionKey);
```

#### Implement Token Rotation
```php
// Rotate refresh tokens periodically
$tokenManager = new TokenManager($provider, $config, $cache);

if ($tokenManager->shouldRotateRefreshToken()) {
    $newToken = $tokenManager->refreshAccessToken();
    // Store new refresh token securely
}
```

#### Short-lived Access Tokens
```php
// Request tokens with minimal lifetime
$token = $tokenManager->getAccessTokenByAuthorizationCode($code, [
    'access_token_lifetime' => 3600, // 1 hour
]);
```

## 2. Performance Best Practices

### Caching Strategy

#### Implement Multi-level Caching
```php
// Memory cache for frequently accessed data
$memoryCache = new ArrayAdapter();

// File cache for persistent data
$fileCache = new FilesystemAdapter('msgraph', 3600, '/tmp/cache');

// Chain caches for optimal performance
$cache = new ChainAdapter([$memoryCache, $fileCache]);
```

#### Cache API Responses
```php
class CachedUserService
{
    use HasCache;

    public function getUser(string $userId): GraphResponse
    {
        return $this->remember("user:{$userId}", function () use ($userId) {
            return $this->userService->getUser($userId);
        }, 3600); // Cache for 1 hour
    }
}
```

#### Cache Document Conversions
```php
class CachedDocumentService
{
    use HasCache;

    public function convertDocument(string $path, string $format): string
    {
        $cacheKey = "conversion:{$path}:{$format}";

        return $this->remember($cacheKey, function () use ($path, $format) {
            return $this->documentService->convertDocument($path, $format);
        }, 86400); // Cache for 24 hours
    }
}
```

### Batch Operations

#### Use Batch Requests for Multiple Operations
```php
$requests = [
    'user' => ['method' => 'GET', 'endpoint' => '/me'],
    'events' => ['method' => 'GET', 'endpoint' => '/me/events', 'query' => ['$top' => 5]],
    'files' => ['method' => 'GET', 'endpoint' => '/me/drive/recent', 'query' => ['$top' => 3]],
];

$responses = $graphClient->batchRequest($requests);
```

#### Batch Similar Operations
```php
// Instead of multiple separate calls
foreach ($userIds as $userId) {
    $users[] = $userService->getUser($userId);
}

// Use batch request
$requests = [];
foreach ($userIds as $userId) {
    $requests["user_{$userId}"] = [
        'method' => 'GET',
        'endpoint' => "/users/{$userId}",
    ];
}
$responses = $graphClient->batchRequest($requests);
```

### Rate Limiting

#### Implement Exponential Backoff
```php
function makeRequestWithRetry(callable $request, int $maxRetries = 3): GraphResponse
{
    $retryCount = 0;

    while ($retryCount < $maxRetries) {
        try {
            return $request();
        } catch (ServiceException $e) {
            if ($e->isRateLimitExceeded() && $retryCount < $maxRetries - 1) {
                $retryAfter = $e->getRetryAfter() ?? pow(2, $retryCount);
                sleep($retryAfter);
                $retryCount++;
            } else {
                throw $e;
            }
        }
    }
}
```

#### Monitor Rate Limit Usage
```php
class RateLimitMonitor
{
    private array $requestCounts = [];
    private int $windowSize = 60; // 1 minute

    public function recordRequest(string $endpoint): void
    {
        $now = time();
        $this->requestCounts[$endpoint][] = $now;

        // Remove old requests outside window
        $this->requestCounts[$endpoint] = array_filter(
            $this->requestCounts[$endpoint],
            fn($timestamp) => ($now - $timestamp) < $this->windowSize
        );
    }

    public function getRemainingRequests(string $endpoint, int $limit = 1000): int
    {
        $count = count($this->requestCounts[$endpoint] ?? []);
        return max(0, $limit - $count);
    }
}
```

## 3. Error Handling Best Practices

### Implement Comprehensive Error Handling
```php
try {
    $response = $service->performOperation();
} catch (AuthenticationException $e) {
    // Handle auth errors
    $this->handleAuthenticationError($e);
} catch (ServiceException $e) {
    // Handle service errors
    $this->handleServiceError($e);
} catch (GraphException $e) {
    // Handle general Graph API errors
    $this->handleGraphError($e);
} catch (Exception $e) {
    // Handle unexpected errors
    $this->handleUnexpectedError($e);
}
```

### Create User-Friendly Error Messages
```php
class ErrorMessageFormatter
{
    public static function format(Exception $e): string
    {
        if ($e instanceof ServiceException) {
            return match ($e->getErrorCode()) {
                ServiceException::ITEM_NOT_FOUND => 'The requested item was not found.',
                ServiceException::INVALID_REQUEST => 'The request was invalid. Please check your input.',
                ServiceException::RATE_LIMIT_EXCEEDED => 'Too many requests. Please try again later.',
                default => 'An error occurred while processing your request.',
            };
        }

        return 'An unexpected error occurred.';
    }
}
```

### Implement Circuit Breaker Pattern
```php
class CircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;
    private const TIMEOUT = 60;

    private array $failures = [];
    private bool $isOpen = false;

    public function call(callable $operation)
    {
        if ($this->isOpen) {
            if (time() - $this->lastFailureTime > self::TIMEOUT) {
                $this->isOpen = false;
            } else {
                throw new ServiceException('Circuit breaker is open');
            }
        }

        try {
            $result = $operation();
            $this->resetFailures();
            return $result;
        } catch (Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function recordFailure(): void
    {
        $this->failures[] = time();
        $this->lastFailureTime = time();

        if (count($this->failures) >= self::FAILURE_THRESHOLD) {
            $this->isOpen = true;
        }
    }

    private function resetFailures(): void
    {
        $this->failures = [];
        $this->isOpen = false;
    }
}
```

## 4. Logging Best Practices

### Implement Structured Logging
```php
use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;

$logger = new Logger('msgraph');
$handler = new StreamHandler('logs/msgraph.log', Logger::INFO);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);

// Log with context
$logger->info('User data retrieved', [
    'user_id' => $userId,
    'operation' => 'getUser',
    'duration' => $duration,
    'cache_hit' => $cacheHit,
]);
```

### Log Sensitive Data Safely
```php
class SafeLogger
{
    public function logUserOperation(string $operation, array $context): void
    {
        // Remove sensitive data from context
        $safeContext = $this->sanitizeContext($context);

        $this->logger->info("User operation: {$operation}", $safeContext);
    }

    private function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'authorization'];

        foreach ($sensitiveKeys as $key) {
            if (isset($context[$key])) {
                $context[$key] = '[REDACTED]';
            }
        }

        return $context;
    }
}
```

### Implement Log Rotation
```php
// Daily log rotation
$handler = new RotatingFileHandler('logs/msgraph.log', 30, Logger::INFO);
$logger->pushHandler($handler);
```

## 5. Testing Best Practices

### Unit Testing
```php
class OneDriveServiceTest extends TestCase
{
    public function testListFiles(): void
    {
        $mockClient = $this->createMock(GraphClient::class);
        $mockResponse = $this->createMock(GraphResponse::class);

        $mockResponse->method('get')->with('value')->willReturn([]);
        $mockClient->method('api')->willReturn($mockResponse);

        $service = new OneDriveService($mockClient);
        $result = $service->listFiles();

        $this->assertInstanceOf(GraphResponse::class, $result);
    }
}
```

### Integration Testing
```php
class IntegrationTest extends TestCase
{
    public function testRealOneDriveOperations(): void
    {
        // Use test credentials
        $config = new AuthConfig([
            'clientId' => getenv('TEST_CLIENT_ID'),
            'clientSecret' => getenv('TEST_CLIENT_SECRET'),
            'tenantId' => getenv('TEST_TENANT_ID'),
        ]);

        $graphClient = new GraphClient($config);
        $oneDrive = new OneDriveService($graphClient);

        // Test real operations
        $files = $oneDrive->listFiles('/');
        $this->assertIsArray($files->get('value'));
    }
}
```

### Mock External Dependencies
```php
class DocumentServiceTest extends TestCase
{
    public function testDocumentConversion(): void
    {
        $mockClient = $this->createMock(GraphClient::class);
        $mockResponse = $this->createMock(GraphResponse::class);

        $mockResponse->method('getBody')->willReturn('mock pdf content');
        $mockClient->method('api')->willReturn($mockResponse);

        $service = new DocumentService($mockClient);
        $result = $service->convertDocxToPdf('/test.docx');

        $this->assertEquals('mock pdf content', $result);
    }
}
```

## 6. Configuration Best Practices

### Environment-Based Configuration
```php
class Configuration
{
    public static function getConfig(): AuthConfig
    {
        return new AuthConfig([
            'clientId' => self::getEnv('AZURE_CLIENT_ID'),
            'clientSecret' => self::getEnv('AZURE_CLIENT_SECRET'),
            'tenantId' => self::getEnv('AZURE_TENANT_ID'),
            'redirectUri' => self::getEnv('AZURE_REDIRECT_URI'),
            'scopes' => self::getScopes(),
            'timeout' => self::getEnv('API_TIMEOUT', 30),
        ]);
    }

    private static function getEnv(string $key, $default = null)
    {
        return getenv($key) ?: $default;
    }

    private static function getScopes(): array
    {
        $envScopes = getenv('AZURE_SCOPES');
        return $envScopes ? explode(',', $envScopes) : [
            'https://graph.microsoft.com/User.Read',
            'https://graph.microsoft.com/Files.ReadWrite.All',
        ];
    }
}
```

### Configuration Validation
```php
class ConfigurationValidator
{
    public static function validate(AuthConfig $config): void
    {
        $errors = [];

        if (empty($config->getClientId())) {
            $errors[] = 'Client ID is required';
        }

        if (empty($config->getClientSecret())) {
            $errors[] = 'Client secret is required';
        }

        if (empty($config->getTenantId())) {
            $errors[] = 'Tenant ID is required';
        }

        if (!filter_var($config->getRedirectUri(), FILTER_VALIDATE_URL)) {
            $errors[] = 'Valid redirect URI is required';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(', ', $errors));
        }
    }
}
```

## 7. Deployment Best Practices

### Container Optimization
```dockerfile
# Use multi-stage build
FROM php:8.1-cli as builder
RUN pecl install redis && docker-php-ext-enable redis

FROM php:8.1-cli-alpine
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Install required extensions
RUN apk add --no-cache git unzip libzip-dev && \
    docker-php-ext-install pcntl bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
```

### Health Checks
```php
class HealthChecker
{
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'microsoft_graph' => $this->checkMicrosoftGraph(),
        ];

        return [
            'status' => in_array(false, $checks) ? 'unhealthy' : 'healthy',
            'checks' => $checks,
        ];
    }

    private function checkMicrosoftGraph(): bool
    {
        try {
            $response = $this->graphClient->api('GET', '/me');
            return $response->isSuccess();
        } catch (Exception $e) {
            return false;
        }
    }
}
```

### Graceful Shutdown
```php
class GracefulShutdown
{
    private bool $shutdown = false;

    public function register(): void
    {
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
    }

    public function handleShutdown(): void
    {
        $this->shutdown = true;
        $this->logger->info('Shutdown signal received');
    }

    public function shouldShutdown(): bool
    {
        return $this->shutdown;
    }
}
```

## 8. Monitoring and Alerting

### Metrics Collection
```php
class MetricsCollector
{
    private array $metrics = [];

    public function record(string $metric, float $value, array $tags = []): void
    {
        $this->metrics[] = [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => time(),
        ];
    }

    public function increment(string $metric, array $tags = []): void
    {
        $this->record($metric, 1, $tags);
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
```

### Performance Monitoring
```php
class PerformanceMonitor
{
    public function monitor(callable $operation, string $operationName): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $result = $operation();
            $success = true;
        } catch (Exception $e) {
            $success = false;
            throw $e;
        } finally {
            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;

            $this->logger->info('Performance metrics', [
                'operation' => $operationName,
                'duration' => $duration,
                'memory_used' => $memoryUsed,
                'success' => $success,
            ]);
        }

        return $result;
    }
}
```

## 9. Documentation Best Practices

### Code Documentation
```php
/**
 * Upload a file to OneDrive with comprehensive error handling
 *
 * @param string $localPath Path to local file
 * @param string $remotePath Target path in OneDrive
 * @param array $options Additional upload options
 * @return GraphResponse
 * @throws ServiceException If file doesn't exist or upload fails
 * @throws AuthenticationException If token is invalid
 *
 * @example
 * $response = $oneDrive->uploadFile('/local/file.pdf', '/remote/file.pdf');
 * if ($response->isSuccess()) {
 *     echo "Upload successful";
 * }
 */
public function uploadFile(string $localPath, string $remotePath, array $options = []): GraphResponse
{
    // Implementation
}
```

### API Documentation
```php
/**
 * @api {post} /me/drive/root:/{path}:/content Upload File
 * @apiName UploadFile
 * @apiGroup OneDrive
 * @apiVersion 1.0.0
 *
 * @apiParam {String} path Remote file path
 * @apiParam {File} file File to upload
 *
 * @apiSuccess {String} id File ID
 * @apiSuccess {String} name File name
 * @apiSuccess {Number} size File size
 *
 * @apiError 401 Unauthorized
 * @apiError 413 Payload Too Large
 */
```

## 10. Maintenance Best Practices

### Dependency Management
```php
// Keep dependencies updated
composer update --with-dependencies

// Security updates only
composer audit
composer update --security
```

### Code Quality
```bash
# Run quality checks
composer run quality

# Fix code style issues
composer run cs:fix

# Run static analysis
composer run phpstan

# Run tests
composer run test:coverage
```

### Performance Profiling
```php
class Profiler
{
    private array $profiles = [];

    public function start(string $name): void
    {
        $this->profiles[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(),
        ];
    }

    public function end(string $name): array
    {
        $profile = $this->profiles[$name];
        $end = microtime(true);
        $memoryEnd = memory_get_usage();

        return [
            'duration' => $end - $profile['start'],
            'memory_used' => $memoryEnd - $profile['memory_start'],
        ];
    }
}
```

## Summary

Following these best practices will help you:

- **Security**: Protect user data and prevent unauthorized access
- **Performance**: Optimize response times and resource usage
- **Reliability**: Handle errors gracefully and maintain service availability
- **Maintainability**: Write clean, testable, and well-documented code
- **Scalability**: Design for growth and high load

Remember to:
- Always validate inputs and handle errors
- Use caching and batching for better performance
- Implement comprehensive logging and monitoring
- Write tests for all critical functionality
- Keep dependencies updated and secure
- Document your code and APIs thoroughly
