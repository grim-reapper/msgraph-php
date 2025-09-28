<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Authentication;

use GrimReapper\MsGraph\Authentication\MicrosoftUser;
use GrimReapper\MsGraph\Authentication\OAuth2Provider;
use GrimReapper\MsGraph\Exceptions\AuthenticationException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class OAuth2ProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected OAuth2Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new OAuth2Provider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_client_secret',
            'redirectUri' => 'http://localhost/redirect',
            'tenantId' => 'mock_tenant_id',
        ]);
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $this->assertStringContainsString('mock_tenant_id/oauth2/v2.0/authorize', $url);
        $this->assertStringContainsString('client_id=mock_client_id', $url);
    }

    public function testBaseAccessTokenUrl(): void
    {
        $url = $this->provider->getBaseAccessTokenUrl([]);
        $this->assertStringContainsString('mock_tenant_id/oauth2/v2.0/token', $url);
    }

    public function testResourceOwnerDetailsUrl(): void
    {
        $token = new AccessToken(['access_token' => 'mock_token']);
        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $this->assertSame('https://graph.microsoft.com/v1.0/me', $url);
    }

    public function testCheckResponseThrowsExceptionOnError(): void
    {
        $this->expectException(IdentityProviderException::class);
        $response = new Response(401, [], json_encode(['error' => 'invalid_client', 'error_description' => 'Invalid client credentials']));

        $reflection = new \ReflectionClass(get_class($this->provider));
        $method = $reflection->getMethod('checkResponse');
        $method->setAccessible(true);
        $data = json_decode((string) $response->getBody(), true);
        $method->invokeArgs($this->provider, [$response, $data]);
    }

    public function testCreateResourceOwner(): void
    {
        $response = ['id' => '12345', 'displayName' => 'Test User'];
        $token = new AccessToken(['access_token' => 'mock_token']);

        $reflection = new \ReflectionClass(get_class($this->provider));
        $method = $reflection->getMethod('createResourceOwner');
        $method->setAccessible(true);
        $user = $method->invokeArgs($this->provider, [$response, $token]);

        $this->assertInstanceOf(MicrosoftUser::class, $user);
        $this->assertSame('12345', $user->getId());
        $this->assertSame('Test User', $user->getDisplayName());
    }

    public function testGetAccessTokenByAuthorizationCode(): void
    {
        $redirectUri = 'http://localhost/redirect';
        $provider = Mockery::mock(OAuth2Provider::class . '[getAccessToken]', [['clientId' => 'mock_client_id', 'redirectUri' => $redirectUri]]);
        $provider->shouldAllowMockingProtectedMethods();

        $expectedParams = [
            'grant_type' => 'authorization_code',
            'code' => 'mock_code',
            'redirect_uri' => $redirectUri,
        ];

        $provider->shouldReceive('getAccessToken')
            ->once()
            ->with('authorization_code', $expectedParams)
            ->andReturn(new AccessToken(['access_token' => 'mock_access_token']));

        $token = $provider->getAccessTokenByAuthorizationCode('mock_code');
        $this->assertSame('mock_access_token', $token->getToken());
    }

    public function testMakeAuthenticatedRequestSuccess(): void
    {
        $mockResponse = new Response(200, [], json_encode(['foo' => 'bar']));
        $mockHttpClient = Mockery::mock(ClientInterface::class);
        $mockHttpClient->shouldReceive('request')->once()->andReturn($mockResponse);
        $this->provider->setHttpClient($mockHttpClient);

        $token = new AccessToken(['access_token' => 'mock_token']);
        $result = $this->provider->makeAuthenticatedRequest('GET', 'http://example.com', $token);

        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testMakeAuthenticatedRequestThrowsException(): void
    {
        $this->expectException(AuthenticationException::class);

        $mockHttpClient = Mockery::mock(ClientInterface::class);
        $mockHttpClient->shouldReceive('request')->once()->andThrow(new \GuzzleHttp\Exception\RequestException('Error Communicating with Server', new \GuzzleHttp\Psr7\Request('GET', 'test')));
        $this->provider->setHttpClient($mockHttpClient);

        $token = new AccessToken(['access_token' => 'mock_token']);
        $this->provider->makeAuthenticatedRequest('GET', 'http://example.com', $token);
    }

    public function testGetUser(): void
    {
        $mockUserData = ['id' => '123', 'displayName' => 'Test User'];
        $mockResponse = new Response(200, [], json_encode($mockUserData));
        $mockHttpClient = Mockery::mock(ClientInterface::class);
        $mockHttpClient->shouldReceive('request')->once()->andReturn($mockResponse);
        $this->provider->setHttpClient($mockHttpClient);

        $token = new AccessToken(['access_token' => 'mock_token']);
        $user = $this->provider->getUser($token);

        $this->assertInstanceOf(MicrosoftUser::class, $user);
        $this->assertSame('123', $user->getId());
    }

    public function testValidateAccessToken(): void
    {
        // Test valid token
        $mockResponse = new Response(200, [], json_encode(['id' => '123']));
        $mockHttpClient = Mockery::mock(ClientInterface::class);
        $mockHttpClient->shouldReceive('request')->once()->andReturn($mockResponse);
        $this->provider->setHttpClient($mockHttpClient);

        $token = new AccessToken(['access_token' => 'valid_token']);
        $this->assertTrue($this->provider->validateAccessToken($token));

        // Test invalid token
        $mockHttpClient = Mockery::mock(ClientInterface::class);
        $mockHttpClient->shouldReceive('request')->once()->andThrow(new AuthenticationException());
        $this->provider->setHttpClient($mockHttpClient);

        $token = new AccessToken(['access_token' => 'invalid_token']);
        $this->assertFalse($this->provider->validateAccessToken($token));
    }
}
