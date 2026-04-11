<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\EvaluationContext;

use Aubes\OpenFeatureBundle\EvaluationContext\UserEvaluationContextProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(UserEvaluationContextProvider::class)]
class UserEvaluationContextProviderTest extends TestCase
{
    public function testReturnsNullWhenNoToken(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $provider = new UserEvaluationContextProvider($tokenStorage);

        $this->assertNull($provider->getContext(Request::create('/')));
    }

    public function testReturnsNullWhenTokenHasNoUser(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $provider = new UserEvaluationContextProvider($tokenStorage);

        $this->assertNull($provider->getContext(Request::create('/')));
    }

    public function testReturnsContextWithTargetingKey(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('john@example.com');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $provider = new UserEvaluationContextProvider($tokenStorage);
        $context = $provider->getContext(Request::create('/'));

        $this->assertNotNull($context);
        $this->assertSame('john@example.com', $context->getTargetingKey());
    }
}
