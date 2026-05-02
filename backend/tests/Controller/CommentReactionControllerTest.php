<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class CommentReactionControllerTest extends ApiTestCase
{
    public function testReactNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_react_nf');
        $this->req('POST', '/api/comments/99999/react', ['type' => 'heart'], 'tok_react_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testReactAccessDenied(): void
    {
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $owner = $this->createMember($family1, 'o@t.com', 'tok_react_owner');
        $other = $this->createMember($family2, 'x@t.com', 'tok_react_other');
        $rec = $this->createRecording($owner);
        $comment = $this->addComment($rec, $owner);

        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'heart'], 'tok_react_other');
        self::assertSame(403, $this->httpStatus());
    }

    public function testReactInvalidType(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_react_invalid');
        $rec = $this->createRecording($member);
        $comment = $this->addComment($rec, $member);

        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'wrong'], 'tok_react_invalid');
        self::assertSame(422, $this->httpStatus());
    }

    public function testReactNewReaction(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_react_new');
        $rec = $this->createRecording($member);
        $comment = $this->addComment($rec, $member);

        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'thumbs_up'], 'tok_react_new');
        self::assertSame(200, $this->httpStatus());

        $data = $this->responseData();
        self::assertSame(1, $data['thumbs_up']);
        self::assertSame('thumbs_up', $data['mine']);
        self::assertSame(['Mitglied'], $data['users']['thumbs_up']);
        self::assertSame([], $data['users']['heart']);
    }

    public function testReactToggleOff(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_react_toggle');
        $rec = $this->createRecording($member);
        $comment = $this->addComment($rec, $member);

        // Add reaction
        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'heart'], 'tok_react_toggle');
        self::assertSame(1, $this->responseData()['heart']);

        // Toggle off (same type)
        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'heart'], 'tok_react_toggle');
        self::assertSame(200, $this->httpStatus());
        self::assertSame(0, $this->responseData()['heart']);
        self::assertNull($this->responseData()['mine']);
    }

    public function testReactChangeType(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_react_change');
        $rec = $this->createRecording($member);
        $comment = $this->addComment($rec, $member);

        // Add thumbs_up
        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'thumbs_up'], 'tok_react_change');
        self::assertSame('thumbs_up', $this->responseData()['mine']);

        // Change to thumbs_down
        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'thumbs_down'], 'tok_react_change');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertSame('thumbs_down', $data['mine']);
        self::assertSame(0, $data['thumbs_up']);
        self::assertSame(1, $data['thumbs_down']);
    }

    public function testReactionUsersShowInCommentList(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_react_users_t');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_react_users_m');
        $rec = $this->createRecording($member);
        $comment = $this->addComment($rec, $member, 'Hello');

        // Teacher reacts
        $this->req('POST', '/api/comments/' . $comment->getId() . '/react', ['type' => 'heart'], 'tok_react_users_t');

        // Fetch comment list — teacher sees own reaction
        $this->req('GET', '/api/recordings/' . $rec->getId() . '/comments', [], 'tok_react_users_t');
        $data = $this->responseData();
        self::assertSame('heart', $data[0]['reactions']['mine']);
        self::assertSame(['Lehrerin'], $data[0]['reactions']['users']['heart']);
    }
}
