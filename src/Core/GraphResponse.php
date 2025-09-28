<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Core;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Microsoft\Graph\Model\Entity;
use Traversable;

/**
 * Enhanced response wrapper for Microsoft Graph API responses.
 */
class GraphResponse implements ArrayAccess, Countable, GraphResponseInterface, IteratorAggregate, JsonSerializable
{
    private mixed $body;
    private int $statusCode;
    private array $headers;

    public function __construct(mixed $body, int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Get the response body.
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Get the raw response content.
     */
    public function getRawResponse()
    {
        return $this->body;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Check if the response was successful (2xx status code).
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if the response indicates an error (4xx or 5xx status code).
     */
    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }

    /**
     * Check if the response is a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if the response is a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Get the response as an array.
     */
    public function toArray(): array
    {
        if (is_array($this->body)) {
            return $this->body;
        }

        if ($this->body instanceof Entity) {
            return $this->body->getProperties();
        }

        return [];
    }

    /**
     * Get the response as JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get a value from the response body using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $array = $this->toArray();

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // Support for dot notation (e.g., 'user.name')
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $array;

            foreach ($keys as $k) {
                if (is_array($value) && array_key_exists($k, $value)) {
                    $value = $value[$k];
                } else {
                    return $default;
                }
            }

            return $value;
        }

        return $default;
    }

    /**
     * Check if a key exists in the response.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get the response body as a specific type.
     */
    public function getTyped(string $className): mixed
    {
        if ($this->body instanceof $className) {
            return $this->body;
        }

        return null;
    }

    /**
     * ArrayAccess implementation.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    /**
     * ArrayAccess implementation.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    /**
     * ArrayAccess implementation.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Read-only implementation
        throw new \RuntimeException('GraphResponse is read-only');
    }

    /**
     * ArrayAccess implementation.
     */
    public function offsetUnset(mixed $offset): void
    {
        // Read-only implementation
        throw new \RuntimeException('GraphResponse is read-only');
    }

    /**
     * Countable implementation.
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * IteratorAggregate implementation.
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * JsonSerializable implementation.
     */
    public function jsonSerialize(): mixed
    {
        return $this->body;
    }

    /**
     * String representation of the response.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Debug information.
     */
    public function __debugInfo(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'headers' => $this->headers,
            'body' => $this->body,
            'bodyType' => gettype($this->body),
        ];
    }
}
