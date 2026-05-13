<?php

namespace App\Controller\Api;

use App\Entity\Attendance;
use App\Entity\Lesson;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lessons')]
class LessonController extends AbstractController
{
    // ── Alle Unterrichtsstunden abrufen ──────────────────────────────────────

    #[Route('', methods: ['GET'])]
    public function list(LessonRepository $repo, AttendanceRepository $attendanceRepo, UserRepository $userRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $lessons = $repo->findBy([], ['date' => 'DESC']);
            $totalStudents = count($userRepo->findBy(['isStudent' => true]));

            return $this->json(array_map(function (Lesson $l) use ($attendanceRepo, $totalStudents) {
                $presentCount = $attendanceRepo->count(['lesson' => $l, 'present' => true]);
                return $this->serializeLesson($l, $presentCount, $totalStudents);
            }, $lessons));
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Neue Unterrichtsstunde anlegen ───────────────────────────────────────

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $dateStr = trim($data['date'] ?? '');
        $title = trim($data['title'] ?? '') ?: null;

        try {
            $date = new \DateTimeImmutable($dateStr);
        } catch (\Exception) {
            return $this->json(['error' => 'Ungültiges Datum.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lesson = new Lesson();
        $lesson->setDate($date)->setTitle($title)->setCreatedBy($user);

        $em->persist($lesson);
        $em->flush();

        return $this->json($this->serializeLesson($lesson, 0, 0), Response::HTTP_CREATED);
    }

    // ── Unterrichtsstunde aktualisieren (homeworkAssigned, title) ────────────

    #[Route('/{id}', methods: ['PATCH'])]
    public function patch(
        int $id,
        Request $request,
        LessonRepository $repo,
        AttendanceRepository $attendanceRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $repo->find($id);
        if (!$lesson) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (array_key_exists('homeworkAssigned', $data)) {
            $lesson->setHomeworkAssigned((bool)$data['homeworkAssigned']);
        }
        if (array_key_exists('title', $data)) {
            $lesson->setTitle(trim($data['title'] ?? '') ?: null);
        }
        if (array_key_exists('summary', $data)) {
            $lesson->setSummary(trim($data['summary'] ?? '') ?: null);
        }

        $em->flush();

        $totalStudents = count($userRepo->findBy(['isStudent' => true]));
        $presentCount  = $attendanceRepo->count(['lesson' => $lesson, 'present' => true]);

        return $this->json($this->serializeLesson($lesson, $presentCount, $totalStudents));
    }

    // ── Unterrichtsstunde löschen ────────────────────────────────────────────

    #[Route('/{id}/delete', methods: ['POST'])]
    public function delete(int $id, LessonRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $repo->find($id);
        if (!$lesson) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($lesson);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Anwesenheit einer Stunde abrufen ─────────────────────────────────────

    #[Route('/{id}/attendance', methods: ['GET'])]
    public function getAttendance(
        int $id,
        LessonRepository $lessonRepo,
        AttendanceRepository $attendanceRepo,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $lessonRepo->find($id);
        if (!$lesson) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $students = $userRepo->findBy(['isStudent' => true], ['familyName' => 'ASC']);

            $attendanceMap = [];
            foreach ($attendanceRepo->findBy(['lesson' => $lesson]) as $a) {
                $attendanceMap[$a->getStudent()->getId()] = $a->isPresent();
            }

            $studentData = array_map(function (User $s) use ($attendanceMap) {
                return [
                    'id'         => $s->getId(),
                    'name'       => $s->getFamilyName(),
                    'familyName' => $s->getFamily()?->getName() ?? $s->getFamilyName(),
                    'present'    => $attendanceMap[$s->getId()] ?? false,
                ];
            }, $students);

            return $this->json([
                'lesson'   => $this->serializeLesson($lesson, array_sum(array_column($studentData, 'present')), count($students)),
                'students' => $studentData,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Anwesenheit eines Schülers setzen (upsert) ───────────────────────────

    #[Route('/{id}/attendance', methods: ['POST'])]
    public function setAttendance(
        int $id,
        Request $request,
        LessonRepository $lessonRepo,
        AttendanceRepository $attendanceRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $lessonRepo->find($id);
        if (!$lesson) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $studentId = (int)($data['studentId'] ?? 0);
        $present = (bool)($data['present'] ?? false);

        $student = $userRepo->find($studentId);
        if (!$student || !$student->isStudent()) {
            return $this->json(['error' => 'Student not found.'], Response::HTTP_NOT_FOUND);
        }

        $attendance = $attendanceRepo->findByLessonAndStudent($id, $studentId);
        if (!$attendance) {
            $attendance = new Attendance();
            $attendance->setLesson($lesson)->setStudent($student);
            $em->persist($attendance);
        }

        $attendance->setPresent($present);
        $em->flush();

        return $this->json(['studentId' => $studentId, 'present' => $present]);
    }

    private function serializeLesson(Lesson $l, int $presentCount, int $totalStudents): array
    {
        return [
            'id'               => $l->getId(),
            'date'             => $l->getDate()->format('Y-m-d'),
            'title'            => $l->getTitle(),
            'summary'          => $l->getSummary(),
            'presentCount'     => $presentCount,
            'totalStudents'    => $totalStudents,
            'homeworkAssigned' => $l->isHomeworkAssigned(),
        ];
    }
}
