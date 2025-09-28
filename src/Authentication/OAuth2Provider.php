<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Authentication;

use GrimReapper\MsGraph\Exceptions\AuthenticationException;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * OAuth 2.0 provider for Microsoft Azure Active Directory.
 */
class OAuth2Provider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public const SCOPE_OPENID = 'openid';
    public const SCOPE_PROFILE = 'profile';
    public const SCOPE_EMAIL = 'email';
    public const SCOPE_OFFLINE_ACCESS = 'offline_access';
    public const SCOPE_FILES = 'https://graph.microsoft.com/Files.ReadWrite.All';
    public const SCOPE_SITES = 'https://graph.microsoft.com/Sites.ReadWrite.All';
    public const SCOPE_MAIL = 'https://graph.microsoft.com/Mail.ReadWrite';
    public const SCOPE_CALENDAR = 'https://graph.microsoft.com/Calendars.ReadWrite';
    public const SCOPE_CONTACTS = 'https://graph.microsoft.com/Contacts.ReadWrite';
    public const SCOPE_TEAMS = 'https://graph.microsoft.com/Team.ReadBasic.All';
    public const SCOPE_USER_READ = 'https://graph.microsoft.com/User.Read';
    public const SCOPE_USER_READ_ALL = 'https://graph.microsoft.com/User.Read.All';
    public const SCOPE_DIRECTORY_READ_ALL = 'https://graph.microsoft.com/Directory.Read.All';

    protected string $openIDConfigurationUrl = 'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration';
    protected string $graphUrl = 'https://graph.microsoft.com';
    protected ?string $tenantId = null;

    public function __construct(array $options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);
        $this->tenantId = $options['tenantId'] ?? null;

        if ($this->tenantId) {
            $this->openIDConfigurationUrl = str_replace(
                'common',
                $this->tenantId,
                $this->openIDConfigurationUrl
            );
        }
    }

    /**
     * Get the OAuth 2.0 authorization URL.
     */
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://login.microsoftonline.com/' . ($this->getTenantId() ?: 'common') . '/oauth2/v2.0/authorize';
    }

    /**
     * Get the OAuth 2.0 access token URL.
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://login.microsoftonline.com/' . ($this->getTenantId() ?: 'common') . '/oauth2/v2.0/token';
    }

    /**
     * Get the URL for refreshing an access token.
     */
    public function getBaseRefreshTokenUrl(array $params): string
    {
        return 'https://login.microsoftonline.com/' . ($this->getTenantId() ?: 'common') . '/oauth2/v2.0/token';
    }

    /**
     * Get the default scopes.
     */
    protected function getDefaultScopes(): array
    {
        return [
            self::SCOPE_OPENID,
            self::SCOPE_PROFILE,
            self::SCOPE_USER_READ,
        ];
    }

    /**
     * Get the tenant ID.
     */
    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * Set the tenant ID.
     */
    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Get the client secret.
     */
    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Get the redirect URI.
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Check the provider response for errors.
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (isset($data['error'])) {
            $error = $data['error'];
            $errorDescription = $data['error_description'] ?? 'Unknown error';

            throw new IdentityProviderException(
                $errorDescription,
                $response->getStatusCode(),
                $data
            );
        }
    }

    /**
     * Generate a random state string.
     */
    protected function getRandomState($length = 32): string
    {
        return parent::getRandomState($length);
    }

    /**
     * Create an access token from a response.
     */
    protected function createAccessToken(array $response, AbstractGrant $grant)
    {
        return new AccessToken($response);
    }

    /**
     * Get the resource owner details URL.
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return $this->graphUrl . '/v1.0/me';
    }

    /**
     * Create a resource owner object from a response.
     */
    protected function createResourceOwner(array $response, AccessToken $token): MicrosoftUser
    {
        return new MicrosoftUser($response);
    }

    /**
     * Get the scopes separator.
     */
    protected function getScopesSeparator(): string
    {
        return ' ';
    }

    /**
     * Get the authorization headers for requests.
     */
    public function getHeaders($token = null): array
    {
        if ($token instanceof AccessToken) {
            $token = $token->getToken();
        }

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Exchange authorization code for access token.
     */
    public function getAccessTokenByAuthorizationCode(string $code, array $options = []): AccessToken
    {
        $params = array_merge([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ], $options);

        return $this->getAccessToken('authorization_code', $params);
    }

    /**
     * Refresh an access token.
     */
    public function refreshAccessToken(string $refreshToken, array $options = []): AccessToken
    {
        $params = array_merge([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], $options);

        return $this->getAccessToken('refresh_token', $params);
    }

    /**
     * Get access token using client credentials flow.
     */
    public function getAccessTokenByClientCredentials(array $options = []): AccessToken
    {
        $params = array_merge([
            'grant_type' => 'client_credentials',
        ], $options);

        return $this->getAccessToken('client_credentials', $params);
    }

    /**
     * Get access token using password flow (not recommended for production).
     */
    public function getAccessTokenByPassword(string $username, string $password, array $options = []): AccessToken
    {
        $params = array_merge([
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ], $options);

        return $this->getAccessToken('password', $params);
    }

    /**
     * Make an authenticated request to Microsoft Graph.
     */
    public function makeAuthenticatedRequest(string $method, string $url, AccessToken $token, array $options = []): array
    {
        $httpClient = $this->getHttpClient();
        $headers = $this->getHeaders($token);

        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $requestOptions = [
            'headers' => $headers,
        ];

        if (isset($options['body'])) {
            $requestOptions['body'] = $options['body'];
        }

        if (isset($options['query'])) {
            $requestOptions['query'] = $options['query'];
        }

        try {
            $response = $httpClient->request($method, $url, $requestOptions);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new AuthenticationException(
                'Failed to make authenticated request: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Get user information.
     */
    public function getUser(AccessToken $token): MicrosoftUser
    {
        $response = $this->makeAuthenticatedRequest('GET', $this->getResourceOwnerDetailsUrl($token), $token);
        return $this->createResourceOwner($response, $token);
    }

    /**
     * Validate the access token.
     */
    public function validateAccessToken(AccessToken $token): bool
    {
        try {
            $this->makeAuthenticatedRequest('GET', $this->getResourceOwnerDetailsUrl($token), $token);
            return true;
        } catch (AuthenticationException $e) {
            return false;
        }
    }
}
