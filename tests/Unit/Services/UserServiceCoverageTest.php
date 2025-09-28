<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Services;

use GrimReapper\MsGraph\Core\GraphClient;
use GrimReapper\MsGraph\Core\GraphResponse;
use GrimReapper\MsGraph\Services\UserService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class UserServiceCoverageTest extends TestCase
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

    public function testGetUserPhotoSuccess(): void
    {
        $userId = '12345';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', "/users/{$userId}/photo/\$value")
            ->andReturn(new GraphResponse('photo_binary_content'));

        $response = $this->userService->getUserPhoto($userId);
        $this->assertSame('photo_binary_content', $response);
    }

    public function testGetUserManagerSuccess(): void
    {
        $userId = '12345';
        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('GET', "/users/{$userId}/manager")
            ->andReturn(new GraphResponse(['id' => 'manager456']));

        $response = $this->userService->getUserManager($userId);
        $this->assertSame('manager456', $response->getBody()['id']);
    }

    public function testUpdateMyProfileSuccess(): void
    {
        $userData = ['displayName' => 'My New Name'];
        $expectedOptions = [
            'body' => json_encode($userData),
            'headers' => ['Content-Type' => 'application/json'],
        ];

        $this->graphClient->shouldReceive('api')
            ->once()
            ->with('PATCH', '/users/me', $expectedOptions)
            ->andReturn(new GraphResponse([], 204));

        $this->userService->updateMyProfile($userData);
    }
}
