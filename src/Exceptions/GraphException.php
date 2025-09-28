<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception class for Microsoft Graph API errors.
 */
class GraphException extends Exception
{
    protected ?string $errorCode = null;
    protected array $errorDetails = [];
    protected int $httpStatusCode = 0;

    public function __construct(
        string $message = '',
        int $code = 0,
        int $httpStatusCode = 0,
        ?string $errorCode = null,
        array $errorDetails = [],
        ?Throwable $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code.
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get the Microsoft Graph error code.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get additional error details from the API response.
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * Check if this is a specific type of error.
     */
    public function isErrorCode(string $code): bool
    {
        return $this->errorCode === $code;
    }

    /**
     * Check if this is a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->httpStatusCode >= 400 && $this->httpStatusCode < 500;
    }

    /**
     * Check if this is a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->httpStatusCode >= 500 && $this->httpStatusCode < 600;
    }

    /**
     * Create a GraphException from API response.
     */
    public static function fromApiResponse(array $response, int $httpStatusCode = 0): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Microsoft Graph API error';
        $code = $error['code'] ?? 'unknown_error';
        $errorDetails = $error;

        return new self(
            $message,
            0,
            $httpStatusCode,
            $code,
            $errorDetails
        );
    }
}
