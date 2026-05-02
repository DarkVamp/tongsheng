<?php

namespace App\Tests\Controller;

use App\Entity\Lesson;
use App\Tests\ApiTestCase;

class LessonControllerTest extends ApiTestCase
{
    private function createLesson(string $date = '2025-06-01', ?string $title = null): Lesson
    {
        $teacher = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['role' => 'teacher']);
        $lesson = new Lesson();
        $lesson->setDate(new \DateTimeImmutable($date))
            ->setTitle($title)
            ->setCreatedBy($teacher);
        $this->em->persist($lesson);
        $this->em->flush();
        return $lesson;
    }

    // ── GET /api/lessons ──────────────────────────────────────────────────────

    public function testListForbiddenForFamilyMember(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_llist_fm');
        $this->req('GET', '/api/lessons', [], 'tok_llist_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testListEmpty(): void
    {
        $this->createTeacher('t@t.com', 'tok_llist_empty');
        $this->req('GET', '/api/lessons', [], 'tok_llist_empty');
        self::assertSame(200, $this->httpStatus());
        self::assertSame([], $this->responseData());
    }

    public function testListWithLessons(): void
    {
        $this->createTeacher('t@t.com', 'tok_llist_ok');
        $this->createLesson('2025-06-01');

        $this->req('GET', '/api/lessons', [], 'tok_llist_ok');
        self::assertSame(200, $this->httpStatus());
        self::assertCount(1, $this->responseData());
    }

    // ── POST /api/lessons ─────────────────────────────────────────────────────

    public function testCreateForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_lcreate_fm');
        $this->req('POST', '/api/lessons', ['date' => '2025-06-01'], 'tok_lcreate_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testCreateInvalidDate(): void
    {
        $this->createTeacher('t@t.com', 'tok_lcreate_baddate');
        $this->req('POST', '/api/lessons', ['date' => 'not-a-date'], 'tok_lcreate_baddate');
        self::assertSame(422, $this->httpStatus());
    }

    public function testCreateSuccessWithoutTitle(): void
    {
        $this->createTeacher('t@t.com', 'tok_lcreate_notitle');
        $this->req('POST', '/api/lessons', ['date' => '2025-06-01'], 'tok_lcreate_notitle');
        self::assertSame(201, $this->httpStatus());
        self::assertSame('2025-06-01', $this->responseData()['date']);
        self::assertNull($this->responseData()['title']);
    }

    public function testCreateSuccessWithTitle(): void
    {
        $this->createTeacher('t@t.com', 'tok_lcreate_title');
        $this->req('POST', '/api/lessons', ['date' => '2025-06-01', 'title' => 'Lektion 5'], 'tok_lcreate_title');
        self::assertSame(201, $this->httpStatus());
        self::assertSame('Lektion 5', $this->responseData()['title']);
    }

    // ── POST /api/lessons/{id}/delete ─────────────────────────────────────────

    public function testDeleteForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_ldel_fm');
        $this->req('POST', '/api/lessons/1/delete', [], 'tok_ldel_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_ldel_nf');
        $this->req('POST', '/api/lessons/99999/delete', [], 'tok_ldel_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteSuccess(): void
    {
        $this->createTeacher('t@t.com', 'tok_ldel_ok');
        $lesson = $this->createLesson();

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/delete', [], 'tok_ldel_ok');
        self::assertSame(204, $this->httpStatus());
    }

    // ── GET /api/lessons/{id}/attendance ──────────────────────────────────────

    public function testAttendanceForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_latt_fm');
        $this->req('GET', '/api/lessons/1/attendance', [], 'tok_latt_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testAttendanceNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_latt_nf');
        $this->req('GET', '/api/lessons/99999/attendance', [], 'tok_latt_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testAttendanceSuccessNoStudents(): void
    {
        $this->createTeacher('t@t.com', 'tok_latt_empty');
        $lesson = $this->createLesson();

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/attendance', [], 'tok_latt_empty');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('lesson', $data);
        self::assertArrayHasKey('students', $data);
        self::assertSame([], $data['students']);
    }

    public function testAttendanceSuccessWithStudents(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_latt_students');
        $family = $this->createFamily('Muster');
        $member = $this->createMember($family, 'm@t.com', 'tok_latt_m');
        // Mark member as student
        $member->setIsStudent(true);
        $this->em->flush();

        $lesson = $this->createLesson();

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/attendance', [], 'tok_latt_students');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertCount(1, $data['students']);
        self::assertFalse($data['students'][0]['present']);
        self::assertSame('Muster', $data['students'][0]['familyName']);
    }

    // ── POST /api/lessons/{id}/attendance ─────────────────────────────────────

    public function testSetAttendanceForbidden(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_setatt_fm');
        $this->req('POST', '/api/lessons/1/attendance', [], 'tok_setatt_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testSetAttendanceLessonNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_setatt_nf');
        $this->req('POST', '/api/lessons/99999/attendance', ['studentId' => 1, 'present' => true], 'tok_setatt_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testSetAttendanceStudentNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_setatt_snf');
        $lesson = $this->createLesson();
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/attendance', ['studentId' => 99999, 'present' => true], 'tok_setatt_snf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testSetAttendanceStudentNotMarkedAsStudent(): void
    {
        $this->createTeacher('t@t.com', 'tok_setatt_notstudent');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_setatt_member');
        // isStudent = false by default
        $lesson = $this->createLesson();

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/attendance', ['studentId' => $member->getId(), 'present' => true], 'tok_setatt_notstudent');
        self::assertSame(404, $this->httpStatus());
    }

    public function testSetAttendanceCreateNew(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_setatt_create');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_setatt_m_create');
        $member->setIsStudent(true);
        $this->em->flush();
        $lesson = $this->createLesson();

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/attendance', ['studentId' => $member->getId(), 'present' => true], 'tok_setatt_create');
        self::assertSame(200, $this->httpStatus());
        self::assertTrue($this->responseData()['present']);
    }

    public function testSetAttendanceUpdateExisting(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_setatt_update');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_setatt_m_update');
        $member->setIsStudent(true);
        $this->em->flush();
        $lesson = $this->createLesson();

        // Create attendance
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/attendance', ['studentId' => $member->getId(), 'present' => true], 'tok_setatt_update');
        self::assertTrue($this->responseData()['present']);

        // Update attendance
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/attendance', ['studentId' => $member->getId(), 'present' => false], 'tok_setatt_update');
        self::assertSame(200, $this->httpStatus());
        self::assertFalse($this->responseData()['present']);
    }
}
