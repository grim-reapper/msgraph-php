<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Exceptions;

use Throwable;

/**
 * Exception thrown when authentication with Microsoft Graph fails.
 */
class AuthenticationException extends GraphException
{
    public const TOKEN_EXPIRED = 'token_expired';
    public const INVALID_TOKEN = 'invalid_token';
    public const INVALID_CLIENT = 'invalid_client';
    public const INVALID_GRANT = 'invalid_grant';
    public const UNAUTHORIZED_CLIENT = 'unauthorized_client';
    public const ACCESS_DENIED = 'access_denied';
    public const UNSUPPORTED_RESPONSE_TYPE = 'unsupported_response_type';
    public const INVALID_SCOPE = 'invalid_scope';
    public const SERVER_ERROR = 'server_error';
    public const TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';

    public function __construct(
        string $message = '',
        int $code = 0,
        int $httpStatusCode = 401,
        ?string $errorCode = null,
        array $errorDetails = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $httpStatusCode, $errorCode, $errorDetails, $previous);
    }

    /**
     * Check if the token has expired.
     */
    public function isTokenExpired(): bool
    {
        return $this->isErrorCode(self::TOKEN_EXPIRED);
    }

    /**
     * Check if the token is invalid.
     */
    public function isTokenInvalid(): bool
    {
        return $this->isErrorCode(self::INVALID_TOKEN);
    }

    /**
     * Check if access was denied.
     */
    public function isAccessDenied(): bool
    {
        return $this->isErrorCode(self::ACCESS_DENIED);
    }

    /**
     * Check if the client credentials are invalid.
     */
    public function isInvalidClient(): bool
    {
        return $this->isErrorCode(self::INVALID_CLIENT);
    }

    /**
     * Check if the requested scope is invalid.
     */
    public function isInvalidScope(): bool
    {
        return $this->isErrorCode(self::INVALID_SCOPE);
    }

    /**
     * Check if this is a temporary server error.
     */
    public function isTemporaryServerError(): bool
    {
        return $this->isErrorCode(self::SERVER_ERROR) || $this->isErrorCode(self::TEMPORARILY_UNAVAILABLE);
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        return match ($this->errorCode) {
            self::TOKEN_EXPIRED => 'Your session has expired. Please sign in again.',
            self::INVALID_TOKEN => 'Authentication token is invalid. Please sign in again.',
            self::ACCESS_DENIED => 'Access denied. You may not have permission to perform this action.',
            self::INVALID_CLIENT => 'Application configuration error. Please contact support.',
            self::INVALID_SCOPE => 'Insufficient permissions. Please contact your administrator.',
            self::SERVER_ERROR => 'Microsoft Graph service is temporarily unavailable. Please try again later.',
            self::TEMPORARILY_UNAVAILABLE => 'Service temporarily unavailable. Please try again in a few minutes.',
            default => 'Authentication failed. Please try signing in again.',
        };
    }
}
