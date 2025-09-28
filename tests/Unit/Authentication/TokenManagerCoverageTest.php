<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Authentication;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Authentication\OAuth2Provider;
use GrimReapper\MsGraph\Authentication\TokenManager;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class TokenManagerCoverageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $provider;
    private $config;
    private $cache;
    private TokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = Mockery::mock(OAuth2Provider::class);
        $this->config = Mockery::mock(AuthConfig::class);
        $this->cache = Mockery::mock(CacheItemPoolInterface::class);

        $this->config->shouldReceive('getClientId')->andReturn('test_client_id');

        $this->tokenManager = new TokenManager($this->provider, $this->config, $this->cache);
    }

    public function testGetClientCredentialsToken(): void
    {
        $newToken = new AccessToken(['access_token' => 'client_creds_token', 'expires' => 3600]);
        $this->provider->shouldReceive('getAccessTokenByClientCredentials')->once()->with([])->andReturn($newToken);

        $this->mockCacheSave();

        $token = $this->tokenManager->getClientCredentialsToken();
        $this->assertSame('client_creds_token', $token->getToken());
    }

    public function testExchangeAuthorizationCode(): void
    {
        $newToken = new AccessToken(['access_token' => 'auth_code_token', 'expires' => 3600]);
        $this->provider->shouldReceive('getAccessTokenByAuthorizationCode')->once()->with('mock_code', [])->andReturn($newToken);

        $this->mockCacheSave();

        $token = $this->tokenManager->exchangeAuthorizationCode('mock_code');
        $this->assertSame('auth_code_token', $token->getToken());
    }

    public function testGetTokenLifetimeWithFutureToken(): void
    {
        $futureTime = time() + 500;
        $this->mockCacheGet(['expires_at' => $futureTime]);
        $this->assertEquals(500, $this->tokenManager->getTokenLifetime(), '', 1.0);
    }

    public function testGetTokenLifetimeWithExpiredToken(): void
    {
        $pastTime = time() - 500;
        $this->mockCacheGet(['expires_at' => $pastTime]);
        $this->assertSame(0, $this->tokenManager->getTokenLifetime());
    }

    public function testGetTokenLifetimeWithNoToken(): void
    {
        $this->mockCacheGet(null);
        $this->assertNull($this->tokenManager->getTokenLifetime());
    }

    public function testHasRefreshTokenReturnsTrueWhenPresent(): void
    {
        $this->mockCacheGet(['refresh_token' => 'some_token']);
        $this->assertTrue($this->tokenManager->hasRefreshToken());
    }

    public function testHasRefreshTokenReturnsFalseWhenMissing(): void
    {
        $this->mockCacheGet(['access_token' => 'some_token']);
        $this->assertFalse($this->tokenManager->hasRefreshToken());
    }

    public function testClearTokenData(): void
    {
        $this->cache->shouldReceive('deleteItem')->once()->with(Mockery::any())->andReturn(true);
        $this->assertTrue($this->tokenManager->clearTokenData());
    }

    private function mockCacheGet($returnData): void
    {
        $cacheItem = Mockery::mock(CacheItemInterface::class);
        $cacheItem->shouldReceive('isHit')->andReturn($returnData !== null);
        $cacheItem->shouldReceive('get')->andReturn($returnData);
        $this->cache->shouldReceive('getItem')->andReturn($cacheItem);
    }

    private function mockCacheSave(): void
    {
        $cacheItem = Mockery::mock(CacheItemInterface::class);
        $this->cache->shouldReceive('getItem')->once()->andReturn($cacheItem);
        $cacheItem->shouldReceive('set')->once()->andReturnSelf();
        $cacheItem->shouldReceive('expiresAfter')->once()->andReturnSelf();
        $this->cache->shouldReceive('save')->once()->with($cacheItem)->andReturn(true);
    }
}
