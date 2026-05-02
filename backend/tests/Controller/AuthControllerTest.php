<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testLoginSuccess(): void
    {
        $this->createTeacher('teacher@test.com', 'tok_login_teacher');

        $this->req('POST', '/api/login', ['email' => 'teacher@test.com', 'password' => 'secret123']);
        self::assertSame(200, $this->httpStatus());

        $data = $this->responseData();
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('id', $data);
        self::assertSame('teacher', $data['role']);
        self::assertSame('Lehrerin', $data['name']);
        self::assertNull($data['familyId']);
    }

    public function testLoginWrongPassword(): void
    {
        $this->createTeacher('teacher@test.com', 'tok_login_bad');

        $this->req('POST', '/api/login', ['email' => 'teacher@test.com', 'password' => 'wrongpassword']);
        self::assertSame(401, $this->httpStatus());
        self::assertSame('Invalid credentials.', $this->responseData()['error']);
    }

    public function testLoginUserNotFound(): void
    {
        $this->req('POST', '/api/login', ['email' => 'nobody@test.com', 'password' => 'anything']);
        self::assertSame(401, $this->httpStatus());
        self::assertSame('Invalid credentials.', $this->responseData()['error']);
    }

    public function testMeReturnsAuthenticatedUser(): void
    {
        $family = $this->createFamily('Muster');
        $member = $this->createMember($family, 'member@test.com', 'tok_me_member');

        $this->req('GET', '/api/me', [], 'tok_me_member');
        self::assertSame(200, $this->httpStatus());

        $data = $this->responseData();
        self::assertSame('Mitglied', $data['name']);
        self::assertSame('family_member', $data['role']);
        self::assertSame($family->getId(), $data['familyId']);
    }

    public function testLogout(): void
    {
        $teacher = $this->createTeacher('teacher@test.com', 'tok_logout');

        $this->req('POST', '/api/logout', [], 'tok_logout');
        self::assertSame(200, $this->httpStatus());
        self::assertSame('Logged out.', $this->responseData()['message']);

        // Token is now null — subsequent requests should fail
        $this->req('GET', '/api/me', [], 'tok_logout');
        self::assertSame(401, $this->httpStatus());
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        $this->req('GET', '/api/me');
        self::assertSame(401, $this->httpStatus());
    }
}
