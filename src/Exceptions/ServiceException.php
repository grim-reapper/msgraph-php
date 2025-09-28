<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Exceptions;

use Throwable;

/**
 * Exception thrown when Microsoft Graph service operations fail.
 */
class ServiceException extends GraphException
{
    public const INVALID_REQUEST = 'invalidRequest';
    public const ITEM_NOT_FOUND = 'itemNotFound';
    public const ACCESS_DENIED = 'accessDenied';
    public const CONFLICT = 'conflict';
    public const UNSUPPORTED_FORMAT = 'unsupportedFormat';
    public const INTERNAL_ERROR = 'internalServerError';
    public const PAYLOAD_TOO_LARGE = 'payloadTooLarge';
    public const SERVICE_UNAVAILABLE = 'serviceUnavailable';
    public const RATE_LIMIT_EXCEEDED = 'rateLimitExceeded';
    public const QUOTA_EXCEEDED = 'quotaExceeded';
    public const INVALID_RANGE = 'invalidRange';
    public const MALFORMED_ENTITY = 'malformedEntity';
    public const METHOD_NOT_ALLOWED = 'methodNotAllowed';
    public const NOT_SUPPORTED = 'notSupported';
    public const REQUEST_TIMEOUT = 'requestTimeout';

    public function __construct(
        string $message = '',
        int $code = 0,
        int $httpStatusCode = 400,
        ?string $errorCode = null,
        array $errorDetails = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $httpStatusCode, $errorCode, $errorDetails, $previous);
    }

    /**
     * Create a ServiceException from API response.
     */
    public static function fromApiResponse(array $response, int $httpStatusCode = 0): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Microsoft Graph service error';
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

    /**
     * Check if the requested item was not found.
     */
    public function isItemNotFound(): bool
    {
        return $this->isErrorCode(self::ITEM_NOT_FOUND) || $this->httpStatusCode === 404;
    }

    /**
     * Check if the service is unavailable.
     */
    public function isServiceUnavailable(): bool
    {
        return $this->isErrorCode(self::SERVICE_UNAVAILABLE) || $this->httpStatusCode === 503;
    }

    /**
     * Check if rate limit was exceeded.
     */
    public function isRateLimitExceeded(): bool
    {
        return $this->isErrorCode(self::RATE_LIMIT_EXCEEDED) || $this->httpStatusCode === 429;
    }

    /**
     * Check if quota was exceeded.
     */
    public function isQuotaExceeded(): bool
    {
        return $this->isErrorCode(self::QUOTA_EXCEEDED);
    }

    /**
     * Check if the request was malformed.
     */
    public function isMalformedRequest(): bool
    {
        return $this->isErrorCode(self::MALFORMED_ENTITY) || $this->httpStatusCode === 400;
    }

    /**
     * Check if the method is not allowed.
     */
    public function isMethodNotAllowed(): bool
    {
        return $this->isErrorCode(self::METHOD_NOT_ALLOWED) || $this->httpStatusCode === 405;
    }

    /**
     * Check if the request timed out.
     */
    public function isRequestTimeout(): bool
    {
        return $this->isErrorCode(self::REQUEST_TIMEOUT) || $this->httpStatusCode === 408;
    }

    /**
     * Check if the operation is not supported.
     */
    public function isNotSupported(): bool
    {
        return $this->isErrorCode(self::NOT_SUPPORTED);
    }

    /**
     * Get retry after header value if available.
     */
    public function getRetryAfter(): ?int
    {
        return $this->errorDetails['retryAfter'] ?? null;
    }

    /**
     * Check if the error is retryable.
     */
    public function isRetryable(): bool
    {
        return $this->isServerError()
            || $this->isRateLimitExceeded()
            || $this->isServiceUnavailable()
            || $this->isRequestTimeout()
            || $this->isTemporaryServerError();
    }

    /**
     * Check if the error is a temporary server error.
     */
    public function isTemporaryServerError(): bool
    {
        return in_array($this->httpStatusCode, [502, 503, 504]);
    }

    /**
     * Get user-friendly error message for service errors.
     */
    public function getUserFriendlyMessage(): string
    {
        if ($this->isItemNotFound()) {
            return 'The requested item was not found.';
        }

        if ($this->isRateLimitExceeded()) {
            $retryAfter = $this->getRetryAfter();
            $message = 'Rate limit exceeded. Please slow down your requests.';
            if ($retryAfter) {
                $message .= " Please try again in {$retryAfter} seconds.";
            }
            return $message;
        }

        if ($this->isServiceUnavailable()) {
            return 'Service is temporarily unavailable. Please try again later.';
        }

        if ($this->isQuotaExceeded()) {
            return 'Usage quota exceeded. Please check your plan limits.';
        }

        if ($this->isMalformedRequest()) {
            return 'Invalid request format. Please check your input data.';
        }

        if ($this->isMethodNotAllowed()) {
            return 'This operation is not allowed for the specified resource.';
        }

        if ($this->isRequestTimeout()) {
            return 'Request timed out. Please try again.';
        }

        if ($this->isNotSupported()) {
            return 'This operation is not supported.';
        }

        return 'Service operation failed. Please try again or contact support if the problem persists.';
    }
}
