<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Authentication;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Configuration class for Microsoft Graph authentication.
 */
class AuthConfig
{
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private string $redirectUri;
    private array $scopes;
    private string $accessToken;
    private ?string $refreshToken = null;
    private ?int $tokenExpiresAt = null;
    private int $timeout = 30;
    private ?CacheItemPoolInterface $cache = null;
    private ?LoggerInterface $logger = null;

    public function __construct(array $config)
    {
        $this->clientId = $config['clientId'] ?? '';
        $this->clientSecret = $config['clientSecret'] ?? '';
        $this->tenantId = $config['tenantId'] ?? '';
        $this->redirectUri = $config['redirectUri'] ?? '';
        $this->scopes = $config['scopes'] ?? ['https://graph.microsoft.com/.default'];
        $this->accessToken = $config['accessToken'] ?? '';
        $this->refreshToken = $config['refreshToken'] ?? null;
        $this->tokenExpiresAt = $config['tokenExpiresAt'] ?? null;
        $this->timeout = $config['timeout'] ?? 30;

        if (isset($config['cache']) && $config['cache'] instanceof CacheItemPoolInterface) {
            $this->cache = $config['cache'];
        }

        if (isset($config['logger']) && $config['logger'] instanceof LoggerInterface) {
            $this->logger = $config['logger'];
        }

        $this->validate();
    }

    /**
     * Validate the configuration.
     */
    private function validate(): void
    {
        if (empty($this->clientId)) {
            throw new \InvalidArgumentException('Client ID is required');
        }

        if (empty($this->clientSecret)) {
            throw new \InvalidArgumentException('Client secret is required');
        }

        if (empty($this->tenantId)) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }

        if (empty($this->redirectUri)) {
            throw new \InvalidArgumentException('Redirect URI is required');
        }

        if (empty($this->scopes)) {
            throw new \InvalidArgumentException('At least one scope is required');
        }
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
     * Get the tenant ID.
     */
    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * Get the redirect URI.
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Get the scopes.
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Get the access token.
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * Set the access token.
     */
    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * Get the refresh token.
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * Set the refresh token.
     */
    public function setRefreshToken(?string $token): void
    {
        $this->refreshToken = $token;
    }

    /**
     * Get the token expiration timestamp.
     */
    public function getTokenExpiresAt(): ?int
    {
        return $this->tokenExpiresAt;
    }

    /**
     * Set the token expiration timestamp.
     */
    public function setTokenExpiresAt(?int $expiresAt): void
    {
        $this->tokenExpiresAt = $expiresAt;
    }

    /**
     * Check if the access token is expired.
     */
    public function isAccessTokenExpired(): bool
    {
        if ($this->tokenExpiresAt === null) {
            return false;
        }

        // Add 5 minute buffer for clock skew
        return time() >= ($this->tokenExpiresAt - 300);
    }

    /**
     * Get the request timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the request timeout.
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Get the cache instance.
     */
    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    /**
     * Set the cache instance.
     */
    public function setCache(?CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Get the logger instance.
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set the logger instance.
     */
    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the OAuth 2.0 authorization URL.
     */
    public function getAuthorizationUrl(array $additionalParams = []): string
    {
        $params = array_merge([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'response_mode' => 'query',
        ], $additionalParams);

        return 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    /**
     * Get the OAuth 2.0 token URL.
     */
    public function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token';
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'tenantId' => $this->tenantId,
            'redirectUri' => $this->redirectUri,
            'scopes' => $this->scopes,
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'tokenExpiresAt' => $this->tokenExpiresAt,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }
}
