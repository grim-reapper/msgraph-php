<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Authentication;

use DateInterval;
use GrimReapper\MsGraph\Exceptions\AuthenticationException;
use GrimReapper\MsGraph\Traits\HasCache;
use GrimReapper\MsGraph\Traits\HasLogging;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manages OAuth 2.0 tokens with secure storage and automatic refresh.
 */
final class TokenManager
{
    use HasCache;
    use HasLogging;

    private OAuth2Provider $provider;
    private AuthConfig $config;
    private string $tokenCacheKey;
    private int $refreshThreshold = 300; // 5 minutes

    public function __construct(
        OAuth2Provider $provider,
        AuthConfig $config,
        ?CacheItemPoolInterface $cache = null,
        ?LoggerInterface $logger = null
    ) {
        $this->provider = $provider;
        $this->config = $config;
        $this->tokenCacheKey = 'msgraph_tokens_' . hash('sha256', $config->getClientId());

        if ($cache !== null) {
            $this->setCache($cache);
        }

        if ($logger !== null) {
            $this->setLogger($logger);
        } else {
            $this->setLogger(new NullLogger());
        }
    }

    /**
     * Get a valid access token, refreshing if necessary.
     */
    public function getAccessToken(): string
    {
        $tokenData = $this->getStoredTokenData();

        if ($tokenData === null || $this->isTokenExpired($tokenData)) {
            if (isset($tokenData['refresh_token'])) {
                $tokenData = $this->refreshAccessToken($tokenData['refresh_token']);
            } else {
                throw new AuthenticationException(
                    'No valid access token available and no refresh token provided'
                );
            }
        }

        return $tokenData['access_token'];
    }

    /**
     * Store token data securely.
     */
    public function storeTokenData(AccessToken $token): void
    {
        $tokenData = $token->getValues();
        $tokenData['access_token'] = $token->getToken();
        $tokenData['refresh_token'] = $token->getRefreshToken();
        $tokenData['expires_at'] = $token->getExpires();

        $this->storeTokenDataArray($tokenData);

        $this->logInfo('Token data stored', [
            'expires_at' => $token->getExpires(),
            'has_refresh_token' => $token->getRefreshToken() !== null,
        ]);
    }

    /**
     * Store token data from array.
     */
    public function storeTokenDataArray(array $tokenData): void
    {
        // Add expiration timestamp if not present
        if (!isset($tokenData['expires_at']) && isset($tokenData['expires'])) {
            $tokenData['expires_at'] = time() + $tokenData['expires'];
        }

        $this->storeInCache($this->tokenCacheKey, $tokenData, $this->getTokenTtl($tokenData));

        $this->logDebug('Token data stored to cache', [
            'key' => $this->tokenCacheKey,
            'expires_at' => $tokenData['expires_at'] ?? null,
        ]);
    }

    /**
     * Get stored token data.
     */
    public function getStoredTokenData(): ?array
    {
        return $this->getFromCache($this->tokenCacheKey);
    }

    /**
     * Clear stored token data.
     */
    public function clearTokenData(): bool
    {
        $this->logInfo('Clearing stored token data');
        return $this->deleteFromCache($this->tokenCacheKey);
    }

    /**
     * Check if current token is expired or will expire soon.
     */
    public function isTokenExpired(?array $tokenData = null): bool
    {
        $tokenData ??= $this->getStoredTokenData();

        if ($tokenData === null) {
            return true;
        }

        $expiresAt = $tokenData['expires_at'] ?? null;

        if ($expiresAt === null) {
            return true;
        }

        return time() >= ($expiresAt - $this->refreshThreshold);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken(?string $refreshToken = null): array
    {
        $tokenData = $this->getStoredTokenData();

        if ($tokenData === null) {
            throw new AuthenticationException('No stored token data available for refresh');
        }

        $refreshToken ??= $tokenData['refresh_token'] ?? null;

        if ($refreshToken === null) {
            throw new AuthenticationException('No refresh token available');
        }

        try {
            $this->logInfo('Refreshing access token');

            $newToken = $this->provider->refreshAccessToken($refreshToken);
            $this->storeTokenData($newToken);

            $tokenValues = $newToken->getValues();
            $tokenValues['access_token'] = $newToken->getToken();
            $tokenValues['refresh_token'] = $newToken->getRefreshToken();
            $tokenValues['expires_at'] = $newToken->getExpires();

            return $tokenValues;
        } catch (\Exception $e) {
            $this->logError('Token refresh failed', [
                'error' => $e->getMessage(),
                'token_expired' => $this->isTokenExpired($tokenData),
            ]);

            // Clear invalid token data
            $this->clearTokenData();

            throw new AuthenticationException(
                'Failed to refresh access token: ' . $e->getMessage(),
                0,
                0,
                AuthenticationException::TOKEN_EXPIRED
            );
        }
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeAuthorizationCode(string $code, array $options = []): AccessToken
    {
        try {
            $this->logInfo('Exchanging authorization code for tokens');

            $token = $this->provider->getAccessTokenByAuthorizationCode($code, $options);
            $this->storeTokenData($token);

            $this->logInfo('Authorization code exchanged successfully', [
                'expires_at' => $token->getExpires(),
                'has_refresh_token' => $token->getRefreshToken() !== null,
            ]);

            return $token;
        } catch (\Exception $e) {
            $this->logError('Authorization code exchange failed', [
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Failed to exchange authorization code: ' . $e->getMessage(),
                0,
                0,
                AuthenticationException::INVALID_GRANT
            );
        }
    }

    /**
     * Get token using client credentials flow.
     */
    public function getClientCredentialsToken(array $options = []): AccessToken
    {
        try {
            $this->logInfo('Getting client credentials token');

            $token = $this->provider->getAccessTokenByClientCredentials($options);
            $this->storeTokenData($token);

            $this->logInfo('Client credentials token obtained successfully', [
                'expires_at' => $token->getExpires(),
            ]);

            return $token;
        } catch (\Exception $e) {
            $this->logError('Client credentials flow failed', [
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Failed to get client credentials token: ' . $e->getMessage(),
                0,
                0,
                AuthenticationException::INVALID_CLIENT
            );
        }
    }

    /**
     * Validate the current access token.
     */
    public function validateAccessToken(): bool
    {
        try {
            $token = $this->getAccessToken();
            return $this->provider->validateAccessToken($token);
        } catch (AuthenticationException $e) {
            $this->logWarning('Access token validation failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the current token's expiration time.
     */
    public function getTokenExpiration(): ?int
    {
        $tokenData = $this->getStoredTokenData();
        return $tokenData['expires_at'] ?? null;
    }

    /**
     * Get the current token's remaining lifetime in seconds.
     */
    public function getTokenLifetime(): ?int
    {
        $expiresAt = $this->getTokenExpiration();

        if ($expiresAt === null) {
            return null;
        }

        $remaining = $expiresAt - time();
        return max(0, $remaining);
    }

    /**
     * Check if a refresh token is available.
     */
    public function hasRefreshToken(): bool
    {
        $tokenData = $this->getStoredTokenData();
        return isset($tokenData['refresh_token']);
    }

    /**
     * Get the refresh token.
     */
    public function getRefreshToken(): ?string
    {
        $tokenData = $this->getStoredTokenData();
        return $tokenData['refresh_token'] ?? null;
    }

    /**
     * Set the refresh threshold in seconds.
     */
    public function setRefreshThreshold(int $seconds): void
    {
        $this->refreshThreshold = $seconds;
    }

    /**
     * Get the refresh threshold in seconds.
     */
    public function getRefreshThreshold(): int
    {
        return $this->refreshThreshold;
    }

    /**
     * Get the cache key used for token storage.
     */
    public function getTokenCacheKey(): string
    {
        return $this->tokenCacheKey;
    }

    /**
     * Get TTL for token storage based on expiration.
     */
    private function getTokenTtl(?array $tokenData): ?DateInterval
    {
        if ($tokenData === null || !isset($tokenData['expires_at'])) {
            return null;
        }

        $expiresAt = $tokenData['expires_at'];
        $now = time();

        if ($expiresAt <= $now) {
            return null;
        }

        $seconds = $expiresAt - $now;
        return new DateInterval('PT' . $seconds . 'S');
    }

    /**
     * Get token information for debugging.
     */
    public function getTokenInfo(): array
    {
        $tokenData = $this->getStoredTokenData();

        if ($tokenData === null) {
            return ['status' => 'no_token'];
        }

        return [
            'status' => $this->isTokenExpired($tokenData) ? 'expired' : 'valid',
            'expires_at' => $tokenData['expires_at'] ?? null,
            'has_refresh_token' => isset($tokenData['refresh_token']),
            'lifetime_seconds' => $this->getTokenLifetime(),
        ];
    }

    /**
     * Auto-refresh token if needed and return updated token data.
     */
    public function ensureValidToken(): array
    {
        $tokenData = $this->getStoredTokenData();

        if ($tokenData === null) {
            throw new AuthenticationException('No token available');
        }

        if ($this->isTokenExpired($tokenData)) {
            $tokenData = $this->refreshAccessToken();
        }

        return $tokenData;
    }
}
