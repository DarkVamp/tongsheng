<?php

namespace App\Tests\Entity;

use App\Entity\Attendance;
use App\Entity\Comment;
use App\Entity\CommentReaction;
use App\Entity\Family;
use App\Entity\HomeworkImage;
use App\Entity\Invitation;
use App\Entity\Lesson;
use App\Entity\Recording;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class EntityTest extends TestCase
{
    // ── User ──────────────────────────────────────────────────────────────────

    public function testUserDefaults(): void
    {
        $user = new User();
        self::assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $user->getRecordings());
        self::assertSame('zh', $user->getLocale());
        self::assertFalse($user->isStudent());
        self::assertNull($user->getApiToken());
        self::assertNull($user->getFamily());
    }

    public function testUserSettersGetters(): void
    {
        $family = new Family();
        $family->setName('Familie');

        $user = new User();
        $user->setEmail('a@b.com')
            ->setFamilyName('Mueller')
            ->setRole('teacher')
            ->setPassword('hashed')
            ->setApiToken('tok123')
            ->setLocale('de')
            ->setIsStudent(true)
            ->setFamily($family);

        self::assertSame('a@b.com', $user->getEmail());
        self::assertSame('Mueller', $user->getFamilyName());
        self::assertSame('teacher', $user->getRole());
        self::assertSame('hashed', $user->getPassword());
        self::assertSame('tok123', $user->getApiToken());
        self::assertSame('de', $user->getLocale());
        self::assertTrue($user->isStudent());
        self::assertSame($family, $user->getFamily());
    }

    public function testUserRoles(): void
    {
        $teacher = new User();
        $teacher->setRole('teacher');
        self::assertSame(['ROLE_TEACHER'], $teacher->getRoles());
        self::assertTrue($teacher->isTeacher());
        self::assertFalse($teacher->isFamilyMember());

        $member = new User();
        $member->setRole('family_member');
        self::assertSame(['ROLE_FAMILY_MEMBER'], $member->getRoles());
        self::assertFalse($member->isTeacher());
        self::assertTrue($member->isFamilyMember());
    }

    public function testUserIdentifierAndEraseCredentials(): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        self::assertSame('test@test.com', $user->getUserIdentifier());
        // eraseCredentials does nothing — just ensure it runs without error
        $user->eraseCredentials();
        self::assertTrue(true);
    }

    public function testCanAccessRecordingAsTeacher(): void
    {
        $teacher = new User();
        $teacher->setRole('teacher');

        $owner = new User();
        $owner->setRole('family_member');

        $recording = new Recording();
        $recording->setUser($owner);

        self::assertTrue($teacher->canAccessRecording($recording));
    }

    public function testCanAccessRecordingSameFamily(): void
    {
        $family = new Family();
        $family->setName('Muster');

        $owner = new User();
        $owner->setRole('family_member')->setFamily($family);

        $viewer = new User();
        $viewer->setRole('family_member')->setFamily($family);

        $recording = new Recording();
        $recording->setUser($owner);

        self::assertTrue($viewer->canAccessRecording($recording));
    }

    public function testCanAccessRecordingDifferentFamily(): void
    {
        $family1 = new Family();
        $family1->setName('A');
        $family2 = new Family();
        $family2->setName('B');

        $owner = new User();
        $owner->setRole('family_member')->setFamily($family1);

        $other = new User();
        $other->setRole('family_member')->setFamily($family2);

        $recording = new Recording();
        $recording->setUser($owner);

        self::assertFalse($other->canAccessRecording($recording));
    }

    public function testCanAccessRecordingNoFamily(): void
    {
        $user = new User();
        $user->setRole('family_member');
        // no family set → $myFamily is null

        $owner = new User();
        $owner->setRole('family_member');

        $recording = new Recording();
        $recording->setUser($owner);

        self::assertFalse($user->canAccessRecording($recording));
    }

    // ── Family ────────────────────────────────────────────────────────────────

    public function testFamilyConstructorAndSetters(): void
    {
        $family = new Family();
        self::assertInstanceOf(\DateTimeImmutable::class, $family->getCreatedAt());
        self::assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $family->getMembers());

        $family->setName('Muster');
        self::assertSame('Muster', $family->getName());
    }

    // ── Recording ─────────────────────────────────────────────────────────────

    public function testRecordingConstructorAndSetters(): void
    {
        $user = new User();
        $recording = new Recording();
        self::assertInstanceOf(\DateTimeImmutable::class, $recording->getRecordedAt());
        self::assertNull($recording->getDeleteAt());
        self::assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $recording->getComments());

        $recording->setUser($user)
            ->setFilename('test.webm')
            ->setMimeType('audio/webm')
            ->setFileSize(2048)
            ->setDeleteAt(new \DateTimeImmutable('+5 weeks'))
            ->setRecordedAt(new \DateTimeImmutable('2025-01-01'));

        self::assertSame($user, $recording->getUser());
        self::assertSame('test.webm', $recording->getFilename());
        self::assertSame('audio/webm', $recording->getMimeType());
        self::assertSame(2048, $recording->getFileSize());
        self::assertNotNull($recording->getDeleteAt());
        self::assertSame('2025-01-01', $recording->getRecordedAt()->format('Y-m-d'));
    }

    // ── Comment ───────────────────────────────────────────────────────────────

    public function testCommentConstructorAndSetters(): void
    {
        $user = new User();
        $recording = new Recording();
        $comment = new Comment();
        self::assertInstanceOf(\DateTimeImmutable::class, $comment->getCreatedAt());
        self::assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $comment->getReactions());
        self::assertNull($comment->getAuthor());

        $comment->setRecording($recording)->setContent('Hello')->setAuthor($user);
        self::assertSame($recording, $comment->getRecording());
        self::assertSame('Hello', $comment->getContent());
        self::assertSame($user, $comment->getAuthor());

        $comment->setAuthor(null);
        self::assertNull($comment->getAuthor());
    }

    // ── CommentReaction ───────────────────────────────────────────────────────

    public function testCommentReactionSetters(): void
    {
        $user = new User();
        $comment = new Comment();
        $reaction = new CommentReaction();
        $reaction->setComment($comment)->setUser($user)->setType('heart');

        self::assertSame($comment, $reaction->getComment());
        self::assertSame($user, $reaction->getUser());
        self::assertSame('heart', $reaction->getType());
    }

    // ── Invitation ────────────────────────────────────────────────────────────

    public function testInvitationConstructor(): void
    {
        $inviter = new User();
        $family = new Family();
        $family->setName('Test');

        $invitation = new Invitation();
        self::assertNotEmpty($invitation->getToken());
        self::assertInstanceOf(\DateTimeImmutable::class, $invitation->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $invitation->getExpiresAt());
        self::assertFalse($invitation->isExpired());
        self::assertSame('family_member', $invitation->getRole());

        $invitation->setEmail('a@b.com')
            ->setRole('teacher')
            ->setFamily($family)
            ->setInvitedBy($inviter);

        self::assertSame('a@b.com', $invitation->getEmail());
        self::assertSame('teacher', $invitation->getRole());
        self::assertSame($family, $invitation->getFamily());
        self::assertSame($inviter, $invitation->getInvitedBy());

        // Test null family
        $invitation->setFamily(null);
        self::assertNull($invitation->getFamily());
    }

    public function testInvitationIsExpired(): void
    {
        $inviter = new User();
        $invitation = new Invitation();
        $invitation->setEmail('a@b.com')->setInvitedBy($inviter);

        // Not yet expired
        self::assertFalse($invitation->isExpired());

        // Force expiry via reflection
        $ref = new \ReflectionProperty(Invitation::class, 'expiresAt');
        $ref->setValue($invitation, new \DateTimeImmutable('-1 day'));

        self::assertTrue($invitation->isExpired());
    }

    // ── Lesson ────────────────────────────────────────────────────────────────

    public function testLessonSetters(): void
    {
        $user = new User();
        $lesson = new Lesson();
        self::assertInstanceOf(\DateTimeImmutable::class, $lesson->getCreatedAt());
        self::assertNull($lesson->getTitle());
        self::assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $lesson->getAttendances());

        $date = new \DateTimeImmutable('2025-06-01');
        $lesson->setDate($date)->setTitle('Lektion 1')->setCreatedBy($user);

        self::assertSame($date, $lesson->getDate());
        self::assertSame('Lektion 1', $lesson->getTitle());
        self::assertSame($user, $lesson->getCreatedBy());

        $lesson->setTitle(null);
        self::assertNull($lesson->getTitle());
    }

    // ── HomeworkImage ─────────────────────────────────────────────────────────

    public function testHomeworkImageConstructorAndSetters(): void
    {
        $lesson = new Lesson();
        $family = new Family();
        $family->setName('Test');

        $img = new HomeworkImage();
        self::assertNull($img->getId());
        self::assertInstanceOf(\DateTimeImmutable::class, $img->getUploadedAt());

        $img->setLesson($lesson)
            ->setFamily($family)
            ->setFilePath('abc123.jpg')
            ->setOriginalFilename('photo.jpg')
            ->setMimeType('image/jpeg');

        self::assertSame($lesson, $img->getLesson());
        self::assertSame($family, $img->getFamily());
        self::assertSame('abc123.jpg', $img->getFilePath());
        self::assertSame('photo.jpg', $img->getOriginalFilename());
        self::assertSame('image/jpeg', $img->getMimeType());
    }

    // ── Attendance ────────────────────────────────────────────────────────────

    public function testAttendanceSetters(): void
    {
        $user = new User();
        $lesson = new Lesson();
        $attendance = new Attendance();
        self::assertFalse($attendance->isPresent());

        $attendance->setLesson($lesson)->setStudent($user)->setPresent(true);
        self::assertSame($lesson, $attendance->getLesson());
        self::assertSame($user, $attendance->getStudent());
        self::assertTrue($attendance->isPresent());
    }
}
