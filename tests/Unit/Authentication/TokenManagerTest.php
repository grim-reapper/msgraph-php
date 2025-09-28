<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Authentication;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Authentication\OAuth2Provider;
use GrimReapper\MsGraph\Authentication\TokenManager;
use GrimReapper\MsGraph\Exceptions\AuthenticationException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class TokenManagerTest extends TestCase
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

    public function testGetAccessTokenWithValidToken(): void
    {
        $tokenData = ['access_token' => 'valid_token', 'expires_at' => time() + 3600];
        $this->mockCacheGet($tokenData);

        $accessToken = $this->tokenManager->getAccessToken();

        $this->assertSame('valid_token', $accessToken);
    }

    public function testGetAccessTokenWithExpiredTokenAndRefreshToken(): void
    {
        $expiredTokenData = ['access_token' => 'expired_token', 'refresh_token' => 'refresh_token', 'expires_at' => time() - 100];
        $cacheItem = $this->mockCacheGet($expiredTokenData);

        $newToken = new AccessToken(['access_token' => 'new_valid_token', 'refresh_token' => 'new_refresh_token', 'expires' => 3600]);
        $this->provider->shouldReceive('refreshAccessToken')->once()->with('refresh_token')->andReturn($newToken);

        // Add expectations for the save operation to the same cache item
        $cacheItem->shouldReceive('set')->once()->andReturnSelf();
        $cacheItem->shouldReceive('expiresAfter')->once()->andReturnSelf();
        $this->cache->shouldReceive('save')->once()->with($cacheItem)->andReturn(true);

        $accessToken = $this->tokenManager->getAccessToken();

        $this->assertSame('new_valid_token', $accessToken);
    }

    public function testGetAccessTokenThrowsExceptionWithNoToken(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->mockCacheGet(null);
        $this->tokenManager->getAccessToken();
    }

    public function testIsTokenExpired(): void
    {
        $this->assertTrue($this->tokenManager->isTokenExpired(['expires_at' => time() - 100]));
        $this->assertTrue($this->tokenManager->isTokenExpired(['expires_at' => time() + 200])); // Within 5min threshold
        $this->assertFalse($this->tokenManager->isTokenExpired(['expires_at' => time() + 600]));
        $this->assertTrue($this->tokenManager->isTokenExpired(null));
    }

    public function testRefreshAccessTokenFailure(): void
    {
        $this->expectException(AuthenticationException::class);

        $tokenData = ['refresh_token' => 'bad_refresh_token'];
        $this->mockCacheGet($tokenData);
        $this->provider->shouldReceive('refreshAccessToken')->once()->andThrow(new \Exception('Refresh failed'));

        $this->cache->shouldReceive('deleteItem')->once()->andReturn(true);

        $this->tokenManager->refreshAccessToken();
    }

    private function mockCacheGet($returnData): CacheItemInterface
    {
        $cacheItem = Mockery::mock(CacheItemInterface::class);
        $cacheItem->shouldReceive('isHit')->andReturn($returnData !== null);
        $cacheItem->shouldReceive('get')->andReturn($returnData);
        $this->cache->shouldReceive('getItem')->andReturn($cacheItem);
        return $cacheItem;
    }
}
