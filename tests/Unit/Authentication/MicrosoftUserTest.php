<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Authentication;

use GrimReapper\MsGraph\Authentication\MicrosoftUser;
use PHPUnit\Framework\TestCase;

class MicrosoftUserTest extends TestCase
{
    private array $userData;
    private MicrosoftUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userData = [
            'id' => '12345',
            'displayName' => 'John Doe',
            'givenName' => 'John',
            'lastName' => 'Doe',
            'userPrincipalName' => 'johndoe@example.com',
            'mail' => 'john.doe@contoso.com',
            'businessPhones' => ['123-456-7890'],
            'accountEnabled' => true,
        ];
        $this->user = new MicrosoftUser($this->userData);
    }

    public function testGettersWithData(): void
    {
        $this->assertSame('12345', $this->user->getId());
        $this->assertSame('John Doe', $this->user->getDisplayName());
        $this->assertSame('John', $this->user->getFirstName());
        $this->assertSame('Doe', $this->user->getLastName());
        $this->assertSame('john.doe@contoso.com', $this->user->getEmail());
        $this->assertSame('johndoe@example.com', $this->user->getUserPrincipalName());
        $this->assertSame('123-456-7890', $this->user->getBusinessPhone());
        $this->assertTrue($this->user->isAccountEnabled());
    }

    public function testGettersWithMissingData(): void
    {
        $user = new MicrosoftUser([]);
        $this->assertSame('', $user->getId());
        $this->assertSame('', $user->getDisplayName());
        $this->assertSame('', $user->getFirstName());
        $this->assertSame('', $user->getLastName());
        $this->assertSame('', $user->getEmail());
        $this->assertSame('', $user->getUserPrincipalName());
        $this->assertSame('', $user->getBusinessPhone());
        $this->assertTrue($user->isAccountEnabled()); // Defaults to true
        $this->assertNull($user->getManager());
        $this->assertSame([], $user->getDirectReports());
    }

    public function testGetEmailFallback(): void
    {
        $userData = ['userPrincipalName' => 'upn@example.com'];
        $user = new MicrosoftUser($userData);
        $this->assertSame('upn@example.com', $user->getEmail());
    }

    public function testIsAccountEnabled(): void
    {
        $user = new MicrosoftUser(['accountEnabled' => true]);
        $this->assertTrue($user->isAccountEnabled());

        $user = new MicrosoftUser(['accountEnabled' => false]);
        $this->assertFalse($user->isAccountEnabled());

        $user = new MicrosoftUser([]);
        $this->assertTrue($user->isAccountEnabled());
    }

    public function testHasPhoto(): void
    {
        $user = new MicrosoftUser(['photo' => 'http://example.com/photo.jpg']);
        $this->assertTrue($user->hasPhoto());

        $user = new MicrosoftUser(['photo' => '']);
        $this->assertFalse($user->hasPhoto());

        $user = new MicrosoftUser([]);
        $this->assertFalse($user->hasPhoto());
    }

    public function testToArrayAndGetRawData(): void
    {
        $this->assertSame($this->userData, $this->user->toArray());
        $this->assertSame($this->userData, $this->user->getRawData());
    }

    public function testToJson(): void
    {
        $json = $this->user->toJson();
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame($this->userData['id'], $decoded['id']);
    }

    public function testGetAndHas(): void
    {
        $this->assertTrue($this->user->has('displayName'));
        $this->assertFalse($this->user->has('nonExistentKey'));

        $this->assertSame('John Doe', $this->user->get('displayName'));
        $this->assertNull($this->user->get('nonExistentKey'));
        $this->assertSame('default', $this->user->get('nonExistentKey', 'default'));
    }

    public function testToString(): void
    {
        $this->assertSame('John Doe', (string) $this->user);

        $user = new MicrosoftUser(['mail' => 'test@example.com', 'id' => '123']);
        $this->assertSame('test@example.com', (string) $user);

        $user = new MicrosoftUser(['id' => '123']);
        $this->assertSame('123', (string) $user);
    }

    public function testGetResourceOwnerId(): void
    {
        $this->assertSame($this->userData['id'], $this->user->getResourceOwnerId());
    }
}
