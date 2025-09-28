<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Core;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphCollectionRequest;
use GrimReapper\MsGraph\Core\GraphResponse;
use Microsoft\Graph\Http\GraphRequest;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class GraphCollectionRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $client;
    private $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(GraphClient::class);
        $this->request = Mockery::mock(GraphRequest::class);
        $this->client->shouldReceive('createRequest')->andReturn($this->request);
    }

    public function testGetAllHandlesPagination(): void
    {
        $collectionRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);

        $response1 = new GraphResponse(['value' => [['id' => 1]], '@odata.nextLink' => '/users?page=2']);
        $response2 = new GraphResponse(['value' => [['id' => 2]]]);

        $this->client->shouldReceive('execute')->twice()
            ->andReturn($response1, $response2);

        $this->request->shouldReceive('setReturnType'); // Allow setReturnType to be called

        $allResults = $collectionRequest->getAll();

        $this->assertCount(2, $allResults->get('value'));
        $this->assertSame([['id' => 1], ['id' => 2]], $allResults->get('value'));
    }

    public function testPaginate(): void
    {
        $collectionRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);
        $response = new GraphResponse([]);

        $this->request->shouldReceive('addQueryParameter')->with('$top', '50')->once();
        $this->request->shouldReceive('addQueryParameter')->with('$skip', '100')->once();
        $this->client->shouldReceive('execute')->with($this->request)->andReturn($response);

        $collectionRequest->paginate(50, 3);
    }

    public function testNextReturnsResponseWhenLinkExists(): void
    {
        $collectionRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);
        $previousResponse = new GraphResponse(['@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users?$skip=10']);

        $this->client->shouldReceive('execute')->once()->andReturn(new GraphResponse([]));

        $nextResponse = $collectionRequest->next($previousResponse);
        $this->assertInstanceOf(GraphResponse::class, $nextResponse);
    }

    public function testNextReturnsNullWhenNoLinkExists(): void
    {
        $collectionRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);
        $previousResponse = new GraphResponse([]);

        $this->assertNull($collectionRequest->next($previousResponse));
    }

    public function testCount(): void
    {
        $collectionRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);
        $response = new GraphResponse(['@odata.count' => 123]);

        $this->request->shouldReceive('addQueryParameter')->with('$count', 'true')->once();
        $this->request->shouldReceive('addQueryParameter')->with('$top', '1')->once();
        $this->client->shouldReceive('execute')->with($this->request)->andReturn($response);

        $count = $collectionRequest->count();
        $this->assertSame(123, $count);
    }

    public function testFilterIsImmutableAndBuildsCorrectUrl(): void
    {
        $originalRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);
        $filteredRequest = $originalRequest->filter("startsWith(displayName, 'A')");

        $this->assertNotSame($originalRequest, $filteredRequest);

        $reflection = new \ReflectionClass($filteredRequest::class);
        $endpointProperty = $reflection->getProperty('endpoint');
        $endpointProperty->setAccessible(true);

        $this->assertStringContainsString('$filter=startsWith%28displayName%2C+%27A%27%29', $endpointProperty->getValue($filteredRequest));
    }

    public function testChainingQueryModifiers(): void
    {
        $collectionRequest = new GraphCollectionRequest($this->client, 'GET', '/users', \stdClass::class);
        $modifiedRequest = $collectionRequest
            ->filter("userType eq 'Member'")
            ->orderBy('displayName')
            ->select('id,displayName');

        $this->client->shouldReceive('execute')->once()->andReturn(new GraphResponse([]));

        $reflection = new \ReflectionClass($modifiedRequest::class);
        $endpointProperty = $reflection->getProperty('endpoint');
        $endpointProperty->setAccessible(true);
        $endpoint = $endpointProperty->getValue($modifiedRequest);

        $this->assertStringContainsString('$filter=userType+eq+%27Member%27', $endpoint);
        $this->assertStringContainsString('&$orderby=displayName', $endpoint);
        $this->assertStringContainsString('&$select=id%2CdisplayName', $endpoint);

        $this->request->shouldReceive('addQueryParameter')->with('$top', '100')->once();
        $modifiedRequest->first();
    }
}
