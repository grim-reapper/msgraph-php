<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Feature;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Authentication\OAuth2Provider;
use GrimReapper\MsGraph\Authentication\TokenManager;
use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Services\UserService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testFullAuthenticationAndApiCallFlow(): void
    {
        // 1. Setup: Mock HTTP responses and create real objects
        $mockHttpHandler = new MockHandler([
            // First response: for exchanging the auth code for a token
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.VA9-I2_32T3aT-2rX-3aT-2rX-3aT-2rX-3aT-2rX',
                'refresh_token' => 'mock_refresh_token',
                'expires_in' => 3600,
            ])),
            // Second response: for the actual API call to /me
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'user123',
                'displayName' => 'Test User',
            ])),
        ]);
        $handlerStack = HandlerStack::create($mockHttpHandler);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $config = new AuthConfig([
            'clientId' => 'test_client_id',
            'clientSecret' => 'test_client_secret',
            'tenantId' => 'test_tenant_id',
            'redirectUri' => 'http://localhost/redirect',
        ]);

        $provider = new OAuth2Provider($config->toArray(), ['httpClient' => $httpClient]);

        // Use an in-memory cache for the test
        $cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $tokenManager = new TokenManager($provider, $config, $cache);

        // 2. Exchange authorization code for a token
        $token = $tokenManager->exchangeAuthorizationCode('mock_auth_code');
        $this->assertSame('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.VA9-I2_32T3aT-2rX-3aT-2rX-3aT-2rX-3aT-2rX', $token->getToken());

        // 3. Verify the token is stored
        $storedTokenData = $tokenManager->getStoredTokenData();
        $this->assertNotNull($storedTokenData);
        $this->assertSame('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.VA9-I2_32T3aT-2rX-3aT-2rX-3aT-2rX-3aT-2rX', $storedTokenData['access_token']);

        // 4. Use the token to make an API call
        // The GraphClient needs the access token from the config. The TokenManager
        // doesn't automatically update the original config object, so we do it manually for the test.
        $config->setAccessToken($tokenManager->getAccessToken());

        $graphClient = new GraphClient($config, $httpClient);
        $userService = new UserService($graphClient);

        // 5. Make the API call and assert the result
        $userResponse = $userService->getCurrentUser();

        $this->assertTrue($userResponse->isSuccess());
        $this->assertSame('Test User', $userResponse->get('displayName'));
    }
}
