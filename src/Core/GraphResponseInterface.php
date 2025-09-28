<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Core;

/**
 * Interface for Microsoft Graph API response handling.
 */
interface GraphResponseInterface
{
    /**
     * Get the raw response body.
     */
    public function getBody();

    /**
     * Get a specific property from the response.
     */
    public function get(string $key, $default = null);

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int;

    /**
     * Get the response headers.
     */
    public function getHeaders(): array;

    /**
     * Check if the response was successful.
     */
    public function isSuccess(): bool;

    /**
     * Get the raw response content.
     */
    public function getRawResponse();
}
