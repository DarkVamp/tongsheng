<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class CommentControllerTest extends ApiTestCase
{
    // ── GET /api/recordings/{id}/comments ─────────────────────────────────────

    public function testListNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_clist_nf');
        $this->req('GET', '/api/recordings/99999/comments', [], 'tok_clist_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testListAccessDenied(): void
    {
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $owner = $this->createMember($family1, 'o@t.com', 'tok_clist_owner');
        $other = $this->createMember($family2, 'x@t.com', 'tok_clist_other');
        $rec = $this->createRecording($owner);

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/comments', [], 'tok_clist_other');
        self::assertSame(403, $this->httpStatus());
    }

    public function testListEmptySuccess(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_clist_empty');
        $rec = $this->createRecording($member);

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/comments', [], 'tok_clist_empty');
        self::assertSame(200, $this->httpStatus());
        self::assertSame([], $this->responseData());
    }

    public function testListWithComments(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_clist_teacher');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_clist_member');
        $rec = $this->createRecording($member);

        $this->addComment($rec, $teacher, 'Sehr gut!');
        $this->addComment($rec, $member, 'Danke!');

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/comments', [], 'tok_clist_teacher');
        self::assertSame(200, $this->httpStatus());

        $data = $this->responseData();
        self::assertCount(2, $data);
        self::assertArrayHasKey('authorName', $data[0]);
        self::assertArrayHasKey('authorRole', $data[0]);
        self::assertArrayHasKey('reactions', $data[0]);
        self::assertArrayHasKey('users', $data[0]['reactions']);
        // Comment with null author (legacy)
        self::assertSame('teacher', $data[0]['authorRole']);
    }

    public function testListCommentWithNullAuthor(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_clist_null_author');
        $rec = $this->createRecording($member);

        // Create comment without author via direct DB manipulation
        $comment = new \App\Entity\Comment();
        $comment->setRecording($rec)->setContent('Old comment');
        // author stays null
        $this->em->persist($comment);
        $this->em->flush();

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/comments', [], 'tok_clist_null_author');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertNull($data[0]['authorName']);
        self::assertNull($data[0]['authorRole']);
    }

    // ── POST /api/recordings/{id}/comments ────────────────────────────────────

    public function testCreateNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_ccreate_nf');
        $this->req('POST', '/api/recordings/99999/comments', ['content' => 'Hi'], 'tok_ccreate_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testCreateAccessDenied(): void
    {
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $owner = $this->createMember($family1, 'o@t.com', 'tok_ccreate_owner');
        $other = $this->createMember($family2, 'x@t.com', 'tok_ccreate_other');
        $rec = $this->createRecording($owner);

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/comments', ['content' => 'Hi'], 'tok_ccreate_other');
        self::assertSame(403, $this->httpStatus());
    }

    public function testCreateEmptyContent(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_ccreate_empty');
        $rec = $this->createRecording($member);

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/comments', ['content' => '  '], 'tok_ccreate_empty');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_ccreate_ok');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_ccreate_member');
        $rec = $this->createRecording($member);

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/comments', ['content' => 'Bravo!'], 'tok_ccreate_ok');
        self::assertSame(201, $this->httpStatus());

        $data = $this->responseData();
        self::assertSame('Bravo!', $data['content']);
        self::assertSame('Lehrerin', $data['authorName']);
        self::assertSame('teacher', $data['authorRole']);
        self::assertSame(0, $data['reactions']['thumbs_up']);
        self::assertSame([], $data['reactions']['users']['heart']);
        self::assertNull($data['reactions']['mine']);
    }
}
