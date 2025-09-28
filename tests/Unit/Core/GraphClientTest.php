<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Core;

use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class GraphClientTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testApiSuccess(): void
    {
        $config = new AuthConfig([
            'clientId' => 'test',
            'clientSecret' => 'test',
            'tenantId' => 'test',
            'redirectUri' => 'http://localhost',
        ]);

        $mockSdkResponse = Mockery::mock(\Microsoft\Graph\Http\GraphResponse::class);
        $mockSdkResponse->shouldReceive('getStatus')->andReturn(200);
        $mockSdkResponse->shouldReceive('getBody')->andReturn(['id' => '123']);

        $mockRequest = Mockery::mock(GraphRequest::class);
        $mockRequest->shouldReceive('execute')->andReturn($mockSdkResponse);
        $mockRequest->shouldReceive('addQueryParameter');
        $mockGraph = Mockery::mock(Graph::class);
        $mockGraph->shouldReceive('setAccessToken');
        $mockGraph->shouldReceive('getLastStatusCode')->andReturn(200);
        $mockGraph->shouldReceive('createRequest')->with('GET', '/v1.0/me')->andReturn($mockRequest);

        $client = new GraphClient($config);
        $reflection = new \ReflectionClass($client);
        $graphProperty = $reflection->getProperty('graph');
        $graphProperty->setAccessible(true);
        $graphProperty->setValue($client, $mockGraph);

        $response = $client->api('GET', '/me', ['query' => ['test' => '1']]);

        $this->assertInstanceOf(GraphResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertSame(['id' => '123'], $response->getBody());
    }

    public function testExecuteThrowsServiceExceptionOnError(): void
    {
        $this->expectException(ServiceException::class);

        $config = new AuthConfig([
            'clientId' => 'test',
            'clientSecret' => 'test',
            'tenantId' => 'test',
            'redirectUri' => 'http://localhost',
        ]);

        $mockRequest = Mockery::mock(GraphRequest::class);
        $mockRequest->shouldReceive('execute')->andThrow(new \GuzzleHttp\Exception\ClientException(
            'Error',
            new \GuzzleHttp\Psr7\Request('GET', '/me'),
            new \GuzzleHttp\Psr7\Response(404)
        ));

        $client = new GraphClient($config);

        // We can't easily mock the internal graph object that creates the request,
        // so we can't fully mock the execute path. Instead, we'll test the exception
        // wrapping by using reflection to inject a mock that throws an exception.

        $mockGraph = Mockery::mock(Graph::class);
        $mockGraph->shouldReceive('setAccessToken');
        $mockGraph->shouldReceive('createRequest')->andReturn($mockRequest);

        $reflection = new \ReflectionClass($client);
        $graphProperty = $reflection->getProperty('graph');
        $graphProperty->setAccessible(true);
        $graphProperty->setValue($client, $mockGraph);

        $client->execute($mockRequest);
    }

    public function testSetAccessToken(): void
    {
        $config = Mockery::mock(AuthConfig::class);
        $config->shouldReceive('getTimeout')->andReturn(30);
        $config->shouldReceive('getTenantId');
        $config->shouldReceive('getClientId');
        $config->shouldReceive('getAccessToken')->andReturn('initial_token');
        $config->shouldReceive('setAccessToken')->with('new_token')->once();

        $graph = Mockery::mock(Graph::class);
        // The initial token is set in the constructor, before we can inject the mock.
        // We only care about the NEW token being set.
        $graph->shouldReceive('setAccessToken')->with('new_token')->once();

        $client = new GraphClient($config);

        $reflection = new \ReflectionClass($client);
        $graphProperty = $reflection->getProperty('graph');
        $graphProperty->setAccessible(true);
        $graphProperty->setValue($client, $graph);

        $client->setAccessToken('new_token');
    }

    public function testBuildUrl(): void
    {
        $config = new AuthConfig([
            'clientId' => 'test',
            'clientSecret' => 'test',
            'tenantId' => 'test',
            'redirectUri' => 'http://localhost',
        ]);
        $client = new GraphClient($config);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('buildUrl');
        $method->setAccessible(true);

        $this->assertSame('/v1.0/users', $method->invoke($client, 'users'));
        $this->assertSame('/v1.0/users', $method->invoke($client, '/users'));
        $this->assertSame('/beta/users', $method->invoke($client, 'beta/users'));
        $this->assertSame('/v1.0/me/drive/root:/document.docx:/content', $method->invoke($client, 'me/drive/root:/document.docx:/content'));
    }
}
