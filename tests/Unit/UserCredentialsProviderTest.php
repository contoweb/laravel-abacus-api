<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Credentials\ProvidesApiCredentials;
use Contoweb\AbacusApi\Credentials\UserCredentialsProvider;
use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Contoweb\AbacusApi\Tests\TestCase;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\Attributes\Test;

class UserCredentialsProviderTest extends TestCase
{
    #[Test]
    public function it_returns_credentials_from_authenticated_user(): void
    {
        $expectedDto = new AbacusApiCredentialsDto(
            'https://user-api.example.com',
            'user-mandate',
            'user-client-id',
            'user-client-secret',
            'v1',
        );

        $user = $this->createMock(UserWithCredentials::class);
        $user->method('abacusCredentials')->willReturn($expectedDto);

        $guard = $this->createMock(Guard::class);
        $guard->method('user')->willReturn($user);

        $provider = new UserCredentialsProvider($guard);
        $credentials = $provider->getCredentials();

        $this->assertSame($expectedDto, $credentials);
        $this->assertEquals('https://user-api.example.com', $credentials->baseUrl);
        $this->assertEquals('user-mandate', $credentials->mandate);
    }

    #[Test]
    public function it_throws_exception_when_user_not_authenticated(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('user')->willReturn(null);

        $provider = new UserCredentialsProvider($guard);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User must implement ProvidesApiCredentials');

        $provider->getCredentials();
    }

    #[Test]
    public function it_throws_exception_when_user_does_not_implement_interface(): void
    {
        $user = $this->createMock(Authenticatable::class);

        $guard = $this->createMock(Guard::class);
        $guard->method('user')->willReturn($user);

        $provider = new UserCredentialsProvider($guard);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User must implement ProvidesApiCredentials');

        $provider->getCredentials();
    }
}

interface UserWithCredentials extends Authenticatable, ProvidesApiCredentials {}
