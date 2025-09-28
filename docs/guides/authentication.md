# Authentication Guide

## Overview

This guide explains how to authenticate with Microsoft Graph API using the Microsoft Graph PHP Package. The package supports OAuth 2.0 with Azure Active Directory (Azure AD) v2.0 endpoint.

## Prerequisites

Before implementing authentication, you need:

1. **Azure AD Application Registration**
   - Register an application in Azure Portal
   - Configure authentication settings
   - Set redirect URI

2. **Required Permissions**
   - Configure API permissions in Azure AD
   - Request user consent for required scopes

3. **PHP Requirements**
   - PHP 8.1 or higher
   - Required extensions (curl, json, mbstring)

## Azure AD Application Setup

### 1. Register Application

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to "Azure Active Directory" > "App registrations"
3. Click "New registration"
4. Enter application details:
   - Name: Your application name
   - Supported account types: Choose based on your needs
   - Redirect URI: `https://yourapp.com/callback` (for web apps)

### 2. Configure Authentication

1. In your registered app, go to "Authentication"
2. Add platform (Web, Single-page application, etc.)
3. Configure redirect URIs
4. Enable public client flows if needed

### 3. Configure API Permissions

1. Go to "API permissions"
2. Click "Add a permission"
3. Select "Microsoft Graph"
4. Choose permission types:
   - **Delegated permissions** (for user-specific operations)
   - **Application permissions** (for app-only operations)

#### Common Permission Scopes

```php
$scopes = [
    // User operations
    'https://graph.microsoft.com/User.Read',
    'https://graph.microsoft.com/User.ReadBasic.All',

    // File operations
    'https://graph.microsoft.com/Files.Read',
    'https://graph.microsoft.com/Files.ReadWrite',
    'https://graph.microsoft.com/Files.ReadWrite.All',
    'https://graph.microsoft.com/Sites.ReadWrite.All',

    // Mail operations
    'https://graph.microsoft.com/Mail.Read',
    'https://graph.microsoft.com/Mail.ReadWrite',
    'https://graph.microsoft.com/Mail.Send',

    // Calendar operations
    'https://graph.microsoft.com/Calendars.Read',
    'https://graph.microsoft.com/Calendars.ReadWrite',

    // OpenID Connect
    'openid',
    'profile',
    'email',
    'offline_access', // For refresh tokens
];
```

## Basic Authentication Flow

### 1. Configuration Setup

```php
use GrimReapper\MsGraph\Authentication\AuthConfig;

$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback',
    'scopes' => [
        'https://graph.microsoft.com/User.Read',
        'https://graph.microsoft.com/Files.ReadWrite.All',
        'openid',
        'offline_access',
    ],
]);
```

### 2. Generate Authorization URL

```php
// Generate authorization URL
$authUrl = $config->getAuthorizationUrl([
    'state' => bin2hex(random_bytes(16)), // CSRF protection
    'prompt' => 'consent', // Force consent screen
]);

// Redirect user to authorization URL
header('Location: ' . $authUrl);
exit;
```

### 3. Handle Authorization Callback

```php
// In your callback handler
if (isset($_GET['code'])) {
    $authorizationCode = $_GET['code'];
    $state = $_GET['state'];

    // Verify state parameter for security
    if ($state !== $_SESSION['oauth_state']) {
        throw new Exception('Invalid state parameter');
    }

    // Exchange authorization code for tokens
    $tokenManager = new TokenManager($oauthProvider, $config);
    $accessToken = $tokenManager->exchangeAuthorizationCode($authorizationCode);

    // Store tokens securely
    $_SESSION['access_token'] = $accessToken->getToken();
    $_SESSION['refresh_token'] = $accessToken->getRefreshToken();
    $_SESSION['expires_at'] = $accessToken->getExpires();

    // Update config with access token
    $config->setAccessToken($accessToken->getToken());
}
```

### 4. Initialize Graph Client

```php
use GrimReapper\MsGraph\Core\GraphClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cache = new FilesystemAdapter('msgraph', 3600, '/path/to/cache');
$graphClient = new GraphClient($config, null, $logger, $cache);
```

## Authentication Methods

### Authorization Code Flow (Recommended)

Best for web applications with server-side code.

```php
// Step 1: Redirect to authorization URL
$authUrl = $config->getAuthorizationUrl([
    'state' => $state,
    'prompt' => 'consent',
]);

// Step 2: Handle callback and exchange code
$token = $tokenManager->exchangeAuthorizationCode($authorizationCode);
$config->setAccessToken($token->getToken());
```

### Client Credentials Flow

For application-only access (daemon services).

```php
$token = $tokenManager->getClientCredentialsToken([
    'scope' => 'https://graph.microsoft.com/.default',
]);
```

### Refresh Token Flow

Automatic token refresh when access token expires.

```php
try {
    $accessToken = $tokenManager->getAccessToken();
} catch (AuthenticationException $e) {
    if ($e->isTokenExpired()) {
        // Token will be automatically refreshed
        $accessToken = $tokenManager->getAccessToken();
    }
}
```

## Security Best Practices

### 1. State Parameter

Always use the state parameter to prevent CSRF attacks:

```php
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$authUrl = $config->getAuthorizationUrl(['state' => $state]);
```

### 2. PKCE (Proof Key for Code Exchange)

For enhanced security in public clients:

```php
// Generate PKCE challenge
$codeVerifier = bin2hex(random_bytes(32));
$codeChallenge = base64url_encode(hash('sha256', $codeVerifier, true));

$authUrl = $config->getAuthorizationUrl([
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256',
]);

// When exchanging code
$token = $tokenManager->exchangeAuthorizationCode($authorizationCode, [
    'code_verifier' => $codeVerifier,
]);
```

### 3. Secure Token Storage

Store tokens securely using encryption:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// Use encrypted cache backend
$cache = new EncryptedFilesystemAdapter($encryptionKey);
$tokenManager = new TokenManager($provider, $config, $cache);
```

### 4. HTTPS Only

Always use HTTPS in production:

```php
// Ensure HTTPS redirect URI
'redirectUri' => 'https://yourapp.com/callback',

// Set secure cookie flags
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
```

## Error Handling

### Authentication Errors

```php
use GrimReapper\MsGraph\Exceptions\AuthenticationException;

try {
    $accessToken = $tokenManager->getAccessToken();
} catch (AuthenticationException $e) {
    switch ($e->getErrorCode()) {
        case AuthenticationException::INVALID_CLIENT:
            // Invalid client credentials
            break;
        case AuthenticationException::INVALID_GRANT:
            // Invalid authorization code or refresh token
            break;
        case AuthenticationException::TOKEN_EXPIRED:
            // Token has expired, will be refreshed automatically
            break;
        default:
            // Other authentication error
    }
}
```

### Common Error Scenarios

1. **Invalid Client Credentials**
   ```php
   // Check Azure AD app registration
   // Verify client ID and secret
   ```

2. **Insufficient Permissions**
   ```php
   // Check API permissions in Azure AD
   // Ensure user has consented to permissions
   ```

3. **Token Expired**
   ```php
   // TokenManager handles automatic refresh
   // Check refresh token is available
   ```

## Advanced Configuration

### Custom HTTP Client

```php
use GuzzleHttp\Client;

$httpClient = new Client([
    'timeout' => 30,
    'headers' => [
        'User-Agent' => 'MyApp/1.0',
    ],
]);

$graphClient = new GraphClient($config, $httpClient, $logger, $cache);
```

### Custom Cache Backend

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

// Redis cache
$cache = new RedisAdapter(RedisAdapter::createConnection('redis://localhost'));

// Memcached cache
$cache = new MemcachedAdapter(MemcachedAdapter::createConnection('memcached://localhost'));

$tokenManager = new TokenManager($provider, $config, $cache);
```

### Custom Logger

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('msgraph');
$logger->pushHandler(new StreamHandler('logs/msgraph.log', Logger::INFO));

$graphClient = new GraphClient($config, null, $logger, $cache);
```

## Testing Authentication

### Mock Authentication for Testing

```php
use PHPUnit\Framework\TestCase;

class AuthenticationTest extends TestCase
{
    public function testAuthenticationFlow()
    {
        // Mock OAuth provider
        $provider = $this->createMock(OAuth2Provider::class);

        // Mock token response
        $token = $this->createMock(AccessToken::class);
        $token->method('getToken')->willReturn('mock-token');

        $provider->method('getAccessTokenByAuthorizationCode')
                ->willReturn($token);

        $tokenManager = new TokenManager($provider, $config);

        $result = $tokenManager->exchangeAuthorizationCode('test-code');
        $this->assertEquals('mock-token', $result->getToken());
    }
}
```

### Integration Testing

```php
class IntegrationTest extends TestCase
{
    public function testRealAuthentication()
    {
        // Use test Azure AD application
        $config = new AuthConfig([
            'clientId' => 'test-client-id',
            'clientSecret' => 'test-client-secret',
            'tenantId' => 'test-tenant-id',
        ]);

        $tokenManager = new TokenManager($provider, $config);

        // Test with real credentials (be careful with this)
        $token = $tokenManager->getClientCredentialsToken();
        $this->assertNotEmpty($token->getToken());
    }
}
```

## Troubleshooting

### Common Issues

1. **"invalid_client" error**
   - Verify client ID and secret
   - Check Azure AD app configuration
   - Ensure redirect URI matches

2. **"access_denied" error**
   - User declined permission consent
   - Insufficient permissions configured
   - Conditional access policies blocking

3. **"token_expired" error**
   - Refresh token has expired
   - User needs to re-authenticate
   - Check token storage and refresh logic

4. **"invalid_scope" error**
   - Requested scopes not configured in Azure AD
   - User hasn't consented to permissions
   - Admin consent required for some permissions

### Debug Mode

Enable debug logging to troubleshoot:

```php
$logger = new Logger('msgraph-debug');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$graphClient = new GraphClient($config, null, $logger, $cache);
```

### Network Issues

Handle network timeouts and retries:

```php
try {
    $response = $graphClient->api('GET', '/me');
} catch (ServiceException $e) {
    if ($e->isRetryable()) {
        // Implement exponential backoff
        sleep(1);
        $response = $graphClient->api('GET', '/me');
    }
}
```

## Production Deployment

### Environment Variables

Use environment variables for sensitive data:

```php
$config = new AuthConfig([
    'clientId' => getenv('AZURE_CLIENT_ID'),
    'clientSecret' => getenv('AZURE_CLIENT_SECRET'),
    'tenantId' => getenv('AZURE_TENANT_ID'),
    'redirectUri' => getenv('AZURE_REDIRECT_URI'),
]);
```

### Secure Token Storage

For production, use secure cache backends:

```php
// Redis with encryption
$redis = new Redis();
$redis->connect('localhost', 6379);
$cache = new RedisAdapter($redis);

// Or database storage
$cache = new DatabaseAdapter($pdo, 'oauth_tokens');
```

### Monitoring

Monitor authentication metrics:

```php
// Track token refresh frequency
$tokenManager->setLogger($logger);

// Monitor API rate limits
$graphClient->setLogger($logger);
```

## Security Checklist

- [ ] Use HTTPS for all authentication flows
- [ ] Implement state parameter for CSRF protection
- [ ] Use PKCE for public clients
- [ ] Store tokens securely with encryption
- [ ] Implement proper session management
- [ ] Validate redirect URIs
- [ ] Monitor for suspicious authentication patterns
- [ ] Implement rate limiting on authentication endpoints
- [ ] Use short-lived access tokens
- [ ] Rotate client secrets regularly

## Support

For authentication issues:

1. Check [Microsoft Graph permissions reference](https://docs.microsoft.com/en-us/graph/permissions-reference)
2. Review [Azure AD authentication flows](https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow)
3. Check [OAuth 2.0 troubleshooting guide](https://docs.microsoft.com/en-us/azure/active-directory/develop/troubleshoot-publisher-verification)

## Next Steps

After setting up authentication:

1. Test with Microsoft Graph Explorer
2. Implement proper error handling
3. Set up logging and monitoring
4. Configure caching for performance
5. Implement rate limiting
6. Set up proper session management
