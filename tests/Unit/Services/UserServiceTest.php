<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Exceptions\ServiceException;
use GrimReapper\MsGraph\Services\UserService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $graphClient;
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphClient = Mockery::mock(GraphClient::class);
        $this->userService = new UserService($this->graphClient);
    }

    public function testGetCurrentUserSuccess(): void
    {
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me')
            ->andReturn(new GraphResponse(['id' => 'me']));

        $response = $this->userService->getCurrentUser();
        $this->assertSame(['id' => 'me'], $response->getBody());
    }

    public function testGetUserSuccess(): void
    {
        $userId = '12345';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', "/users/{$userId}")
            ->andReturn(new GraphResponse(['id' => $userId]));

        $response = $this->userService->getUser($userId);
        $this->assertSame(['id' => $userId], $response->getBody());
    }

    public function testSearchUsersSuccess(): void
    {
        $query = 'testuser';
        $expectedQuery = [
            '$search' => '"' . $query . '"',
            '$top' => 25,
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/users', ['query' => $expectedQuery])
            ->andReturn(new GraphResponse(['value' => [['displayName' => 'Test User']]]));

        $this->userService->searchUsers($query);
    }

    public function testUpdateUserSuccess(): void
    {
        $userId = '12345';
        $userData = ['displayName' => 'New Name'];
        $expectedOptions = [
            'body' => json_encode($userData),
            'headers' => ['Content-Type' => 'application/json'],
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('PATCH', "/users/{$userId}", $expectedOptions)
            ->andReturn(new GraphResponse([], 204));

        $this->userService->updateUser($userId, $userData);
    }

    public function testGetCurrentUserThrowsServiceExceptionOnFailure(): void
    {
        $this->expectException(ServiceException::class);

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', '/me')
            ->andThrow(new \GuzzleHttp\Exception\RequestException('Error', new \GuzzleHttp\Psr7\Request('GET', '/me')));

        $this->userService->getCurrentUser();
    }

    public function testUpdateUserPhotoThrowsExceptionIfFileNotExists(): void
    {
        $this->expectException(ServiceException::class);
        $this->userService->updateUserPhoto('123', '/path/to/nonexistent/file.jpg');
    }
}
