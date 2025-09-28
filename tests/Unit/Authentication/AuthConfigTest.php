<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Authentication;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class AuthConfigTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = [
            'clientId' => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'tenantId' => 'test_tenant_id',
            'redirectUri' => 'http://localhost/redirect',
            'scopes' => ['test_scope'],
            'accessToken' => 'test_access_token',
            'refreshToken' => 'test_refresh_token',
            'tokenExpiresAt' => time() + 3600,
            'timeout' => 60,
        ];
    }

    public function testConstructorHappyPath(): void
    {
        $authConfig = new AuthConfig($this->config);
        $this->assertSame($this->config['clientId'], $authConfig->getClientId());
        $this->assertSame($this->config['clientSecret'], $authConfig->getClientSecret());
        $this->assertSame($this->config['tenantId'], $authConfig->getTenantId());
        $this->assertSame($this->config['redirectUri'], $authConfig->getRedirectUri());
        $this->assertSame($this->config['scopes'], $authConfig->getScopes());
        $this->assertSame($this->config['accessToken'], $authConfig->getAccessToken());
        $this->assertSame($this->config['refreshToken'], $authConfig->getRefreshToken());
        $this->assertSame($this->config['tokenExpiresAt'], $authConfig->getTokenExpiresAt());
        $this->assertSame($this->config['timeout'], $authConfig->getTimeout());
    }

    public function testConstructorThrowsExceptionOnMissingClientId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Client ID is required');
        unset($this->config['clientId']);
        new AuthConfig($this->config);
    }

    public function testGettersAndSetters(): void
    {
        $authConfig = new AuthConfig($this->config);

        $authConfig->setAccessToken('new_access_token');
        $this->assertSame('new_access_token', $authConfig->getAccessToken());

        $authConfig->setRefreshToken('new_refresh_token');
        $this->assertSame('new_refresh_token', $authConfig->getRefreshToken());

        $newExpiresAt = time() + 7200;
        $authConfig->setTokenExpiresAt($newExpiresAt);
        $this->assertSame($newExpiresAt, $authConfig->getTokenExpiresAt());

        $authConfig->setTimeout(120);
        $this->assertSame(120, $authConfig->getTimeout());
    }

    public function testIsAccessTokenExpired(): void
    {
        $authConfig = new AuthConfig($this->config);

        // Not expired
        $authConfig->setTokenExpiresAt(time() + 3600);
        $this->assertFalse($authConfig->isAccessTokenExpired());

        // Expired
        $authConfig->setTokenExpiresAt(time() - 100);
        $this->assertTrue($authConfig->isAccessTokenExpired());

        // Within buffer
        $authConfig->setTokenExpiresAt(time() + 200);
        $this->assertTrue($authConfig->isAccessTokenExpired());
    }

    public function testGetAuthorizationUrl(): void
    {
        $authConfig = new AuthConfig($this->config);
        $url = $authConfig->getAuthorizationUrl();
        $this->assertStringContainsString('https://login.microsoftonline.com/test_tenant_id/oauth2/v2.0/authorize', $url);
        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%2Fredirect', $url);
        $this->assertStringContainsString('scope=test_scope', $url);
    }

    public function testGetTokenUrl(): void
    {
        $authConfig = new AuthConfig($this->config);
        $this->assertSame('https://login.microsoftonline.com/test_tenant_id/oauth2/v2.0/token', $authConfig->getTokenUrl());
    }

    public function testToArray(): void
    {
        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        $this->config['cache'] = $cacheMock;
        $this->config['logger'] = $loggerMock;

        $authConfig = new AuthConfig($this->config);
        $array = $authConfig->toArray();

        $this->assertIsArray($array);
        $this->assertSame($this->config['clientId'], $array['clientId']);
        $this->assertArrayNotHasKey('cache', $array);
        $this->assertArrayNotHasKey('logger', $array);
    }
}
