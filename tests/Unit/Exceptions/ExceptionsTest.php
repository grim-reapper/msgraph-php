<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Exceptions;

use GrimReapper\MsGraph\Exceptions\GraphException;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function testGraphExceptionHelpers(): void
    {
        $clientError = new GraphException('Error', 0, 404, 'itemNotFound');
        $this->assertTrue($clientError->isClientError());
        $this->assertFalse($clientError->isServerError());
        $this->assertTrue($clientError->isErrorCode('itemNotFound'));
        $this->assertFalse($clientError->isErrorCode('accessDenied'));
        $this->assertSame(404, $clientError->getHttpStatusCode());
        $this->assertSame('itemNotFound', $clientError->getErrorCode());
    }

    public function testServiceExceptionIsItemNotFound(): void
    {
        $byCode = new ServiceException('', 0, 500, 'itemNotFound');
        $this->assertTrue($byCode->isItemNotFound());

        $byHttpCode = new ServiceException('', 0, 404, 'someOtherCode');
        $this->assertTrue($byHttpCode->isItemNotFound());
    }

    public function testServiceExceptionIsRateLimitExceeded(): void
    {
        $byCode = new ServiceException('', 0, 500, 'rateLimitExceeded');
        $this->assertTrue($byCode->isRateLimitExceeded());

        $byHttpCode = new ServiceException('', 0, 429, 'someOtherCode');
        $this->assertTrue($byHttpCode->isRateLimitExceeded());
    }

    public function testServiceExceptionIsRetryable(): void
    {
        $serverError = new ServiceException('', 0, 503);
        $this->assertTrue($serverError->isRetryable());

        $rateLimitError = new ServiceException('', 0, 429);
        $this->assertTrue($rateLimitError->isRetryable());

        $clientError = new ServiceException('', 0, 404);
        $this->assertFalse($clientError->isRetryable());
    }
}
