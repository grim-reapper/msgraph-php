<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Core;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GuzzleHttp\Client as GuzzleClient;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Interface for Microsoft Graph API client operations.
 */
interface GraphClientInterface
{
    /**
     * Create a new GraphRequest for API calls.
     */
    public function createRequest(string $method, string $endpoint): GraphRequest;

    /**
     * Execute a GraphRequest and return the response.
     */
    public function execute(GraphRequest $request): GraphResponseInterface;

    /**
     * Make a direct API call with automatic error handling.
     */
    public function api(string $method, string $endpoint, array $options = []): GraphResponseInterface;

    /**
     * Get the underlying Microsoft Graph SDK instance.
     */
    public function getGraph(): Graph;

    /**
     * Get the HTTP client instance.
     */
    public function getHttpClient(): GuzzleClient;

    /**
     * Get the authentication configuration.
     */
    public function getConfig(): AuthConfig;

    /**
     * Get the logger instance.
     */
    public function getLogger(): LoggerInterface;

    /**
     * Get the cache instance.
     */
    public function getCache(): ?CacheItemPoolInterface;

    /**
     * Update the access token.
     */
    public function setAccessToken(string $token): void;

    /**
     * Create a collection request for paginated results.
     */
    public function createCollectionRequest(
        string $method,
        string $endpoint,
        string $collectionClass
    ): GraphCollectionRequest;

    /**
     * Batch multiple requests.
     */
    public function batchRequest(array $requests): array;
}
