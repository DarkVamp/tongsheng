<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class UserControllerTest extends ApiTestCase
{
    public function testUpdateLocaleValidDe(): void
    {
        $this->createTeacher('t@t.com', 'tok_locale');
        $this->req('PATCH', '/api/me/locale', ['locale' => 'de'], 'tok_locale');
        self::assertSame(200, $this->httpStatus());
        self::assertSame('de', $this->responseData()['locale']);
    }

    public function testUpdateLocaleValidZh(): void
    {
        $this->createTeacher('t@t.com', 'tok_locale2');
        $this->req('PATCH', '/api/me/locale', ['locale' => 'zh'], 'tok_locale2');
        self::assertSame(200, $this->httpStatus());
    }

    public function testUpdateLocaleInvalid(): void
    {
        $this->createTeacher('t@t.com', 'tok_locale3');
        $this->req('PATCH', '/api/me/locale', ['locale' => 'fr'], 'tok_locale3');
        self::assertSame(422, $this->httpStatus());
        self::assertArrayHasKey('error', $this->responseData());
    }

    public function testToggleStudentForbiddenForFamilyMember(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'member@t.com', 'tok_toggle_forbidden');
        $this->req('POST', '/api/users/' . $member->getId() . '/toggle-student', [], 'tok_toggle_forbidden');
        self::assertSame(403, $this->httpStatus());
    }

    public function testToggleStudentNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_toggle_nf');
        $this->req('POST', '/api/users/99999/toggle-student', [], 'tok_toggle_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testToggleStudentCannotMarkTeacher(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_toggle_teacher');
        $teacher2 = $this->createTeacher('t2@t.com', 'tok_target_teacher');
        $this->req('POST', '/api/users/' . $teacher2->getId() . '/toggle-student', [], 'tok_toggle_teacher');
        self::assertSame(400, $this->httpStatus());
    }

    public function testToggleStudentSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_toggle_ok');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'member@t.com', 'tok_member_ok');

        // Mark as student
        $this->req('POST', '/api/users/' . $member->getId() . '/toggle-student', [], 'tok_toggle_ok');
        self::assertSame(200, $this->httpStatus());
        self::assertTrue($this->responseData()['isStudent']);

        // Unmark
        $this->req('POST', '/api/users/' . $member->getId() . '/toggle-student', [], 'tok_toggle_ok');
        self::assertSame(200, $this->httpStatus());
        self::assertFalse($this->responseData()['isStudent']);
    }
}
