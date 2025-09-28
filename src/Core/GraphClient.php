<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Core;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main client for Microsoft Graph API operations.
 */
class GraphClient implements GraphClientInterface
{
    private Graph $graph;
    private GuzzleClient $httpClient;
    private AuthConfig $config;
    private LoggerInterface $logger;
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(
        AuthConfig $config,
        ?GuzzleClient $httpClient = null,
        ?LoggerInterface $logger = null,
        ?CacheItemPoolInterface $cache = null
    ) {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->cache = $cache;

        // Initialize HTTP client
        $this->httpClient = $httpClient ?? new GuzzleClient([
            'timeout' => $config->getTimeout(),
            'headers' => [
                'User-Agent' => 'GrimReapper-MsGraph-PHP/1.0',
                'Accept' => 'application/json',
            ],
        ]);

        // Initialize Microsoft Graph SDK
        $this->graph = new Graph();
        $this->graph->setAccessToken($config->getAccessToken());

        $this->logger->info('GraphClient initialized', [
            'tenant_id' => $config->getTenantId(),
            'client_id' => $config->getClientId(),
        ]);
    }

    /**
     * Create a new GraphRequest for API calls.
     */
    public function createRequest(string $method, string $endpoint): GraphRequest
    {
        $url = $this->buildUrl($endpoint);

        $this->logger->debug('Creating Graph request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => $url,
        ]);

        return $this->graph->createRequest($method, $url);
    }

    /**
     * Execute a GraphRequest and return the response.
     */
    public function execute(GraphRequest $request): GraphResponse
    {
        try {
            $this->logger->debug('Executing Graph request');

            $sdkResponse = $request->execute($this->httpClient);
            $statusCode = $sdkResponse->getStatus();
            $body = $sdkResponse->getBody();

            $this->logger->debug('Graph request completed', [
                'status_code' => $statusCode,
            ]);

            return new GraphResponse($body, $statusCode);
        } catch (GuzzleException $e) {
            $this->logger->error('Graph request failed', [
                'error' => $e->getMessage(),
            ]);

            throw ServiceException::fromApiResponse([
                'error' => [
                    'code' => 'request_failed',
                    'message' => $e->getMessage(),
                ],
            ], $e->getCode());
        }
    }

    /**
     * Make a direct API call with automatic error handling.
     */
    public function api(string $method, string $endpoint, array $options = []): GraphResponse
    {
        $request = $this->createRequest($method, $endpoint);

        // Add query parameters if provided
        if (isset($options['query'])) {
            foreach ($options['query'] as $key => $value) {
                $request->addQueryParameter($key, $value);
            }
        }

        // Add headers if provided
        if (isset($options['headers'])) {
            foreach ($options['headers'] as $key => $value) {
                $request->addHeaders([$key => $value]);
            }
        }

        // Add body if provided
        if (isset($options['body'])) {
            $request->attachBody($options['body']);
        }

        return $this->execute($request);
    }

    /**
     * Get the underlying Microsoft Graph SDK instance.
     */
    public function getGraph(): Graph
    {
        return $this->graph;
    }

    /**
     * Get the HTTP client instance.
     */
    public function getHttpClient(): GuzzleClient
    {
        return $this->httpClient;
    }

    /**
     * Get the authentication configuration.
     */
    public function getConfig(): AuthConfig
    {
        return $this->config;
    }

    /**
     * Get the logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get the cache instance.
     */
    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    /**
     * Update the access token.
     */
    public function setAccessToken(string $token): void
    {
        $this->config->setAccessToken($token);
        $this->graph->setAccessToken($token);

        $this->logger->info('Access token updated');
    }

    /**
     * Build the full URL for the API endpoint.
     */
    private function buildUrl(string $endpoint): string
    {
        // If the endpoint already contains the base URL, don't modify it
        if (str_starts_with($endpoint, 'https://') || str_starts_with($endpoint, 'http://')) {
            return $endpoint;
        }

        // Remove leading slash if present
        $endpoint = ltrim($endpoint, '/');

        // Add version prefix if not present
        if (!str_starts_with($endpoint, 'v1.0/') && !str_starts_with($endpoint, 'beta/')) {
            $endpoint = 'v1.0/' . $endpoint;
        }

        return '/' . $endpoint;
    }

    /**
     * Create a collection request for paginated results.
     */
    public function createCollectionRequest(
        string $method,
        string $endpoint,
        string $collectionClass
    ): GraphCollectionRequest {
        return new GraphCollectionRequest($this, $method, $endpoint, $collectionClass);
    }

    /**
     * Batch multiple requests.
     */
    public function batchRequest(array $requests): array
    {
        $batchRequests = [];

        foreach ($requests as $id => $request) {
            $batchRequests[] = [
                'id' => $id,
                'method' => $request['method'],
                'url' => $this->buildUrl($request['endpoint']),
                'body' => $request['body'] ?? null,
                'headers' => $request['headers'] ?? [],
            ];
        }

        $response = $this->api('POST', '/$batch', [
            'body' => json_encode(['requests' => $batchRequests]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        return $response->getBody()['responses'] ?? [];
    }
}
