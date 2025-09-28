<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Core;

/**
 * Handles paginated collection requests to Microsoft Graph API.
 */
class GraphCollectionRequest
{
    private GraphClient $client;
    private string $method;
    private string $endpoint;
    private string $collectionClass;

    public function __construct(
        GraphClient $client,
        string $method,
        string $endpoint,
        string $collectionClass
    ) {
        $this->client = $client;
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->collectionClass = $collectionClass;
    }

    /**
     * Execute the collection request and return all results.
     */
    public function getAll(): GraphResponse
    {
        $allResults = [];
        $nextLink = null;

        do {
            $request = $this->client->createRequest($this->method, $nextLink ?? $this->endpoint);
            $response = $this->client->execute($request);

            $data = $response->getBody();
            $results = $data['value'] ?? [];

            if (is_array($results)) {
                $allResults = array_merge($allResults, $results);
            }

            $nextLink = $data['@odata.nextLink'] ?? null;
        } while ($nextLink !== null);

        return new GraphResponse([
            'value' => $allResults,
            '@odata.count' => count($allResults),
        ], 200);
    }

    /**
     * Get results with pagination support.
     */
    public function paginate(int $pageSize = 100, int $page = 1): GraphResponse
    {
        $skip = ($page - 1) * $pageSize;

        $request = $this->client->createRequest($this->method, $this->endpoint);
        $request->addQueryParameter('$top', (string) $pageSize);
        $request->addQueryParameter('$skip', (string) $skip);

        return $this->client->execute($request);
    }

    /**
     * Get the first page of results.
     */
    public function first(int $count = 100): GraphResponse
    {
        $request = $this->client->createRequest($this->method, $this->endpoint);
        $request->addQueryParameter('$top', (string) $count);

        return $this->client->execute($request);
    }

    /**
     * Get the next page from a previous response.
     */
    public function next(GraphResponse $previousResponse): ?GraphResponse
    {
        $nextLink = $previousResponse->get('@odata.nextLink');

        if (!$nextLink) {
            return null;
        }

        // Extract the endpoint from the next link
        $urlParts = parse_url($nextLink);
        $endpoint = $urlParts['path'] ?? '';

        // Remove the base URL to get just the endpoint
        $endpoint = str_replace('/v1.0', '', $endpoint);
        $endpoint = str_replace('/beta', '', $endpoint);

        if ($urlParts['query'] ?? false) {
            $endpoint .= '?' . $urlParts['query'];
        }

        $request = $this->client->createRequest($this->method, $endpoint);
        return $this->client->execute($request);
    }

    /**
     * Count total items in the collection.
     */
    public function count(): int
    {
        $request = $this->client->createRequest($this->method, $this->endpoint);
        $request->addQueryParameter('$count', 'true');
        $request->addQueryParameter('$top', '1');

        $response = $this->client->execute($request);
        return $response->get('@odata.count', 0);
    }

    /**
     * Filter results using OData filter syntax.
     */
    public function filter(string $filter): self
    {
        $clone = clone $this;
        $clone->endpoint .= (str_contains($clone->endpoint, '?') ? '&' : '?') . '$filter=' . urlencode($filter);
        return $clone;
    }

    /**
     * Order results using OData orderby syntax.
     */
    public function orderBy(string $orderBy): self
    {
        $clone = clone $this;
        $clone->endpoint .= (str_contains($clone->endpoint, '?') ? '&' : '?') . '$orderby=' . urlencode($orderBy);
        return $clone;
    }

    /**
     * Select specific fields using OData select syntax.
     */
    public function select(string $select): self
    {
        $clone = clone $this;
        $clone->endpoint .= (str_contains($clone->endpoint, '?') ? '&' : '?') . '$select=' . urlencode($select);
        return $clone;
    }

    /**
     * Expand related entities using OData expand syntax.
     */
    public function expand(string $expand): self
    {
        $clone = clone $this;
        $clone->endpoint .= (str_contains($clone->endpoint, '?') ? '&' : '?') . '$expand=' . urlencode($expand);
        return $clone;
    }

    /**
     * Search results using OData search syntax.
     */
    public function search(string $search): self
    {
        $clone = clone $this;
        $clone->endpoint .= (str_contains($clone->endpoint, '?') ? '&' : '?') . '$search=' . urlencode($search);
        return $clone;
    }
}
