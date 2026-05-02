<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class FamilyControllerTest extends ApiTestCase
{
    // ── GET /api/families ─────────────────────────────────────────────────────

    public function testListForbiddenForFamilyMember(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_flist_fm');
        $this->req('GET', '/api/families', [], 'tok_flist_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testListSuccess(): void
    {
        $this->createTeacher('t@t.com', 'tok_flist_ok');
        $this->createFamily('Muster');
        $this->createFamily('Schmidt');
        $this->req('GET', '/api/families', [], 'tok_flist_ok');
        self::assertSame(200, $this->httpStatus());
        self::assertCount(2, $this->responseData());
    }

    // ── POST /api/families ────────────────────────────────────────────────────

    public function testCreateForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_fcreate_fm');
        $this->req('POST', '/api/families', ['name' => 'X'], 'tok_fcreate_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testCreateEmptyName(): void
    {
        $this->createTeacher('t@t.com', 'tok_fcreate_empty');
        $this->req('POST', '/api/families', ['name' => '  '], 'tok_fcreate_empty');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateSuccess(): void
    {
        $this->createTeacher('t@t.com', 'tok_fcreate_ok');
        $this->req('POST', '/api/families', ['name' => 'Neu'], 'tok_fcreate_ok');
        self::assertSame(201, $this->httpStatus());
        self::assertSame('Neu', $this->responseData()['name']);
    }

    // ── POST /api/families/{id}/delete ────────────────────────────────────────

    public function testDeleteFamilyForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_fdel_fm');
        $this->req('POST', '/api/families/' . $family->getId() . '/delete', [], 'tok_fdel_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteFamilyNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fdel_nf');
        $this->req('POST', '/api/families/99999/delete', [], 'tok_fdel_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteFamilyWithMembersAndFiles(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fdel_ok');
        $family = $this->createFamily('ToBe Deleted');
        $member = $this->createMember($family, 'm@t.com', 'tok_fdel_member');

        // Create recording dir for member
        $memberDir = self::TEST_RECORDINGS_DIR . '/' . $member->getId();
        mkdir($memberDir, 0755, true);
        file_put_contents($memberDir . '/audio.webm', 'data');

        $this->req('POST', '/api/families/' . $family->getId() . '/delete', [], 'tok_fdel_ok');
        self::assertSame(204, $this->httpStatus());
        self::assertFileDoesNotExist($memberDir . '/audio.webm');
    }

    public function testDeleteFamilyWithNoMemberDirs(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fdel_nodirs');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_fdel_m_nodirs');
        // No recordings dir created

        $this->req('POST', '/api/families/' . $family->getId() . '/delete', [], 'tok_fdel_nodirs');
        self::assertSame(204, $this->httpStatus());
    }

    // ── POST /api/families/{id}/members ───────────────────────────────────────

    public function testCreateMemberForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_memcreate_fm');
        $this->req('POST', '/api/families/' . $family->getId() . '/members', ['name' => 'X', 'email' => 'x@t.com', 'password' => 'pass123'], 'tok_memcreate_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testCreateMemberFamilyNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_memcreate_fnf');
        $this->req('POST', '/api/families/99999/members', ['name' => 'X', 'email' => 'x@t.com', 'password' => 'pass123'], 'tok_memcreate_fnf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testCreateMemberEmptyName(): void
    {
        $this->createTeacher('t@t.com', 'tok_memcreate_noname');
        $family = $this->createFamily();
        $this->req('POST', '/api/families/' . $family->getId() . '/members', ['name' => '', 'email' => 'x@t.com', 'password' => 'pass123'], 'tok_memcreate_noname');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateMemberInvalidEmail(): void
    {
        $this->createTeacher('t@t.com', 'tok_memcreate_bademail');
        $family = $this->createFamily();
        $this->req('POST', '/api/families/' . $family->getId() . '/members', ['name' => 'Neu', 'email' => 'notanemail', 'password' => 'pass123'], 'tok_memcreate_bademail');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateMemberShortPassword(): void
    {
        $this->createTeacher('t@t.com', 'tok_memcreate_shortpw');
        $family = $this->createFamily();
        $this->req('POST', '/api/families/' . $family->getId() . '/members', ['name' => 'Neu', 'email' => 'n@t.com', 'password' => '123'], 'tok_memcreate_shortpw');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateMemberEmailConflict(): void
    {
        $this->createTeacher('t@t.com', 'tok_memcreate_conflict');
        $family = $this->createFamily();
        $this->createMember($family, 'existing@t.com', 'tok_existing');

        $this->req('POST', '/api/families/' . $family->getId() . '/members', ['name' => 'Neu', 'email' => 'existing@t.com', 'password' => 'pass123'], 'tok_memcreate_conflict');
        self::assertSame(409, $this->httpStatus());
    }

    public function testCreateMemberSuccess(): void
    {
        $this->createTeacher('t@t.com', 'tok_memcreate_ok');
        $family = $this->createFamily();
        $this->req('POST', '/api/families/' . $family->getId() . '/members', ['name' => 'Neu', 'email' => 'neu@t.com', 'password' => 'pass123'], 'tok_memcreate_ok');
        self::assertSame(201, $this->httpStatus());
        self::assertSame('Neu', $this->responseData()['name']);
    }

    // ── POST /api/families/members/{id}/delete ────────────────────────────────

    public function testDeleteMemberForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_memdel_fm');
        $this->req('POST', '/api/families/members/' . $member->getId() . '/delete', [], 'tok_memdel_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteMemberNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_memdel_nf');
        $this->req('POST', '/api/families/members/99999/delete', [], 'tok_memdel_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteMemberTargetIsTeacherReturns404(): void
    {
        $teacher1 = $this->createTeacher('t1@t.com', 'tok_memdel_teacher1');
        $teacher2 = $this->createTeacher('t2@t.com', 'tok_memdel_teacher2');
        $this->req('POST', '/api/families/members/' . $teacher2->getId() . '/delete', [], 'tok_memdel_teacher1');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteMemberWithFiles(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_memdel_files');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_memdel_member');

        $memberDir = self::TEST_RECORDINGS_DIR . '/' . $member->getId();
        mkdir($memberDir, 0755, true);
        file_put_contents($memberDir . '/audio.webm', 'data');

        $this->req('POST', '/api/families/members/' . $member->getId() . '/delete', [], 'tok_memdel_files');
        self::assertSame(204, $this->httpStatus());
        self::assertFileDoesNotExist($memberDir . '/audio.webm');
    }

    public function testDeleteMemberNoFiles(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_memdel_nofiles');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_memdel_m_nofiles');

        $this->req('POST', '/api/families/members/' . $member->getId() . '/delete', [], 'tok_memdel_nofiles');
        self::assertSame(204, $this->httpStatus());
    }

    // ── GET /api/families/members ─────────────────────────────────────────────

    public function testMembersForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_fmembers_fm');
        $this->req('GET', '/api/families/members', [], 'tok_fmembers_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testMembersSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fmembers_ok');
        $family = $this->createFamily('Muster');
        $member = $this->createMember($family, 'm@t.com', 'tok_fmembers_m');

        $this->req('GET', '/api/families/members', [], 'tok_fmembers_ok');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertCount(1, $data);
        self::assertSame('Muster', $data[0]['familyName']);
        self::assertCount(1, $data[0]['members']);
    }
}
