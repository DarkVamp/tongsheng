<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ApiTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class ApiTokenAuthenticatorTest extends TestCase
{
    private function makeAuth(?User $userToReturn = null): ApiTokenAuthenticator
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByApiToken')->willReturn($userToReturn);
        return new ApiTokenAuthenticator($repo);
    }

    public function testSupportsWithValidBearerHeader(): void
    {
        $auth = $this->makeAuth();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer mytoken');
        self::assertTrue($auth->supports($request));
    }

    public function testSupportsReturnsFalseWithoutHeader(): void
    {
        $auth = $this->makeAuth();
        $request = new Request();
        self::assertFalse($auth->supports($request));
    }

    public function testSupportsReturnsFalseWithNonBearerHeader(): void
    {
        $auth = $this->makeAuth();
        $request = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');
        self::assertFalse($auth->supports($request));
    }

    public function testAuthenticateWithValidToken(): void
    {
        $user = new User();
        $user->setEmail('a@b.com')->setFamilyName('Test')->setRole('teacher')->setPassword('x');

        $auth = $this->makeAuth($user);
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer validtoken');

        $passport = $auth->authenticate($request);
        self::assertInstanceOf(\Symfony\Component\Security\Http\Authenticator\Passport\Passport::class, $passport);
    }

    public function testAuthenticateThrowsOnInvalidToken(): void
    {
        $auth = $this->makeAuth(null);
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer badtoken');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $passport = $auth->authenticate($request);
        // Force the UserBadge loader to run
        $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)->getUser();
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $auth = $this->makeAuth();
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $result = $auth->onAuthenticationSuccess(new Request(), $token, 'api');
        self::assertNull($result);
    }

    public function testOnAuthenticationFailureReturns401(): void
    {
        $auth = $this->makeAuth();
        $exception = new AuthenticationException('Bad token.');
        $response = $auth->onAuthenticationFailure(new Request(), $exception);
        self::assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testStartReturns401(): void
    {
        $auth = $this->makeAuth();
        $response = $auth->start(new Request());
        self::assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('Authentication required.', $data['error']);
    }
}
