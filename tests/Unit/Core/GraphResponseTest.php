<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Core;

use GrimReapper\MsGraph\Core\GraphResponse;
use Microsoft\Graph\Model\Entity;
use PHPUnit\Framework\TestCase;

class GraphResponseTest extends TestCase
{
    private array $body;
    private GraphResponse $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->body = [
            'id' => '123',
            'user' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
            ],
            'roles' => ['admin', 'editor'],
        ];
        $this->response = new GraphResponse($this->body, 200, ['Content-Type' => 'application/json']);
    }

    public function testConstructorAndGetters(): void
    {
        $this->assertSame($this->body, $this->response->getBody());
        $this->assertSame(200, $this->response->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json'], $this->response->getHeaders());
        $this->assertSame('application/json', $this->response->getHeader('Content-Type'));
        $this->assertNull($this->response->getHeader('Non-Existent'));
    }

    public function testStatusChecks(): void
    {
        $successResponse = new GraphResponse([], 200);
        $this->assertTrue($successResponse->isSuccess());
        $this->assertFalse($successResponse->isError());

        $clientErrorResponse = new GraphResponse([], 404);
        $this->assertFalse($clientErrorResponse->isSuccess());
        $this->assertTrue($clientErrorResponse->isError());
        $this->assertTrue($clientErrorResponse->isClientError());
        $this->assertFalse($clientErrorResponse->isServerError());

        $serverErrorResponse = new GraphResponse([], 503);
        $this->assertFalse($serverErrorResponse->isSuccess());
        $this->assertTrue($serverErrorResponse->isError());
        $this->assertFalse($serverErrorResponse->isClientError());
        $this->assertTrue($serverErrorResponse->isServerError());
    }

    public function testToArray(): void
    {
        $this->assertSame($this->body, $this->response->toArray());

        $mockEntity = $this->createMock(Entity::class);
        $mockEntity->method('getProperties')->willReturn(['prop' => 'value']);
        $entityResponse = new GraphResponse($mockEntity);
        $this->assertSame(['prop' => 'value'], $entityResponse->toArray());
    }

    public function testGetWithDotNotation(): void
    {
        $this->assertSame('John Doe', $this->response->get('user.name'));
        $this->assertSame(['name' => 'John Doe', 'email' => 'john.doe@example.com'], $this->response->get('user'));
        $this->assertNull($this->response->get('user.non_existent'));
        $this->assertSame('default', $this->response->get('user.non_existent', 'default'));
    }

    public function testArrayAccess(): void
    {
        $this->assertTrue(isset($this->response['user']));
        $this->assertSame('John Doe', $this->response['user.name']);
        $this->assertSame($this->body['user'], $this->response['user']);
    }

    public function testArrayAccessIsReadOnly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->response['id'] = 'new_id';
    }

    public function testCountable(): void
    {
        $this->assertCount(3, $this->response);
    }

    public function testIteratorAggregate(): void
    {
        $items = [];
        foreach ($this->response as $key => $value) {
            $items[$key] = $value;
        }
        $this->assertSame($this->body, $items);
    }

    public function testJsonSerializable(): void
    {
        $this->assertSame(json_encode($this->body), json_encode($this->response));
    }

    public function testToString(): void
    {
        $this->assertJsonStringEqualsJsonString(json_encode($this->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), (string) $this->response);
    }
}
