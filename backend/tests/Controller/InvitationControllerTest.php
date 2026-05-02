<?php

namespace App\Tests\Controller;

use App\Entity\Invitation;
use App\Tests\ApiTestCase;

class InvitationControllerTest extends ApiTestCase
{
    private function createExpiredInvitation(\App\Entity\User $teacher, \App\Entity\Family $family): Invitation
    {
        $invitation = new Invitation();
        $invitation->setEmail('expired@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($invitation);
        $this->em->flush();

        // Force expiry
        $this->em->getConnection()->executeStatement(
            'UPDATE invitations SET expires_at = ? WHERE id = ?',
            [(new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'), $invitation->getId()]
        );
        $this->em->clear();
        return $invitation;
    }

    // ── GET /api/invitations ──────────────────────────────────────────────────

    public function testListForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_invlist_fm');
        $this->req('GET', '/api/invitations', [], 'tok_invlist_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testListSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_invlist_ok');
        $family = $this->createFamily();

        $invitation = new Invitation();
        $invitation->setEmail('new@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($invitation);
        $this->em->flush();

        $this->req('GET', '/api/invitations', [], 'tok_invlist_ok');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertCount(1, $data);
        self::assertArrayHasKey('token', $data[0]);
    }

    // ── POST /api/invitations ─────────────────────────────────────────────────

    public function testCreateInvitationForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_invcreate_fm');
        $this->req('POST', '/api/invitations', ['email' => 'x@t.com', 'familyId' => $family->getId()], 'tok_invcreate_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testCreateInvitationInvalidEmail(): void
    {
        $this->createTeacher('t@t.com', 'tok_invcreate_bademail');
        $family = $this->createFamily();
        $this->req('POST', '/api/invitations', ['email' => 'notanemail', 'familyId' => $family->getId()], 'tok_invcreate_bademail');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateInvitationNoFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_invcreate_nofam');
        $this->req('POST', '/api/invitations', ['email' => 'x@t.com'], 'tok_invcreate_nofam');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateInvitationFamilyNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_invcreate_fnf');
        $this->req('POST', '/api/invitations', ['email' => 'x@t.com', 'familyId' => 99999], 'tok_invcreate_fnf');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateInvitationEmailConflictWithUser(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_invcreate_userconflict');
        $family = $this->createFamily();
        $this->createMember($family, 'existing@t.com', 'tok_existing_m');
        $this->req('POST', '/api/invitations', ['email' => 'existing@t.com', 'familyId' => $family->getId()], 'tok_invcreate_userconflict');
        self::assertSame(409, $this->httpStatus());
    }

    public function testCreateInvitationEmailConflictWithInvitation(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_invcreate_invconflict');
        $family = $this->createFamily();

        $inv = new Invitation();
        $inv->setEmail('pending@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($inv);
        $this->em->flush();

        $this->req('POST', '/api/invitations', ['email' => 'pending@t.com', 'familyId' => $family->getId()], 'tok_invcreate_invconflict');
        self::assertSame(409, $this->httpStatus());
    }

    public function testCreateInvitationSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_invcreate_ok');
        $family = $this->createFamily();
        $this->req('POST', '/api/invitations', ['email' => 'new@t.com', 'familyId' => $family->getId()], 'tok_invcreate_ok');
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('token', $data);
        self::assertSame('new@t.com', $data['email']);
    }

    // ── POST /api/invitations/{id}/delete ─────────────────────────────────────

    public function testDeleteInvitationForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_invdel_fm');
        $this->req('POST', '/api/invitations/1/delete', [], 'tok_invdel_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteInvitationNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_invdel_nf');
        $this->req('POST', '/api/invitations/99999/delete', [], 'tok_invdel_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteInvitationSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_invdel_ok');
        $family = $this->createFamily();
        $inv = new Invitation();
        $inv->setEmail('del@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($inv);
        $this->em->flush();

        $this->req('POST', '/api/invitations/' . $inv->getId() . '/delete', [], 'tok_invdel_ok');
        self::assertSame(204, $this->httpStatus());
    }

    // ── GET /api/register/validate ────────────────────────────────────────────

    public function testValidateInvalidToken(): void
    {
        $this->req('GET', '/api/register/validate', [], '', [], ['token' => 'badtoken']);
        // Public endpoint — no auth needed
        $this->client->request('GET', '/api/register/validate?token=badtoken');
        self::assertSame(404, $this->httpStatus());
    }

    public function testValidateExpiredToken(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_validate_teacher');
        $family = $this->createFamily();
        $inv = $this->createExpiredInvitation($teacher, $family);

        $this->client->request('GET', '/api/register/validate?token=' . $inv->getToken());
        self::assertSame(404, $this->httpStatus());
    }

    public function testValidateSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_validate_ok');
        $family = $this->createFamily('Muster');
        $inv = new Invitation();
        $inv->setEmail('new@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($inv);
        $this->em->flush();

        $this->client->request('GET', '/api/register/validate?token=' . $inv->getToken());
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertSame('new@t.com', $data['email']);
        self::assertSame('Muster', $data['familyName']);
    }

    // ── POST /api/register ────────────────────────────────────────────────────

    public function testRegisterInvalidToken(): void
    {
        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => 'bad', 'familyName' => 'X', 'password' => 'pass123']));
        self::assertSame(404, $this->httpStatus());
    }

    public function testRegisterExpiredToken(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_reg_expired');
        $family = $this->createFamily();
        $inv = $this->createExpiredInvitation($teacher, $family);

        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $inv->getToken(), 'familyName' => 'X', 'password' => 'pass123']));
        self::assertSame(404, $this->httpStatus());
    }

    public function testRegisterEmptyName(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_reg_noname');
        $family = $this->createFamily();
        $inv = new Invitation();
        $inv->setEmail('reg@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($inv);
        $this->em->flush();

        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $inv->getToken(), 'familyName' => '', 'password' => 'pass123']));
        self::assertSame(422, $this->httpStatus());
    }

    public function testRegisterShortPassword(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_reg_shortpw');
        $family = $this->createFamily();
        $inv = new Invitation();
        $inv->setEmail('reg2@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($inv);
        $this->em->flush();

        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $inv->getToken(), 'familyName' => 'Max', 'password' => '123']));
        self::assertSame(422, $this->httpStatus());
    }

    public function testRegisterEmailConflict(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_reg_conflict');
        $family = $this->createFamily();
        $inv = new Invitation();
        $inv->setEmail('t@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        // Email 't@t.com' is already registered as the teacher
        $this->em->persist($inv);
        $this->em->flush();

        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $inv->getToken(), 'familyName' => 'Max', 'password' => 'pass123']));
        self::assertSame(409, $this->httpStatus());
    }

    public function testRegisterSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_reg_ok');
        $family = $this->createFamily();
        $inv = new Invitation();
        $inv->setEmail('newuser@t.com')->setRole('family_member')->setFamily($family)->setInvitedBy($teacher);
        $this->em->persist($inv);
        $this->em->flush();

        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $inv->getToken(), 'familyName' => 'Neu', 'password' => 'pass123']));
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('token', $data);
        self::assertSame('family_member', $data['role']);
        self::assertSame('Neu', $data['name']);
    }
}
