<?php

namespace App\Controller\Api;

use App\Entity\HomeworkAudio;
use App\Entity\User;
use App\Repository\HomeworkAudioRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeworkAudioController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'audio/mpeg', 'audio/mp3',
        'audio/mp4', 'audio/x-m4a', 'audio/aac',
        'audio/webm', 'video/webm',
        'audio/ogg', 'audio/vorbis',
        'audio/wav', 'audio/x-wav', 'audio/wave',
        'video/mp4',
        'audio/3gpp', 'video/3gpp',
    ];
    private const MAX_FILE_SIZE  = 50 * 1024 * 1024; // 50 MB
    private const AUDIO_TYPES    = ['lesen', 'sonstiges'];

    public function __construct(
        #[Autowire('%app.homework_audio_dir%')]
        private string $audioDir
    ) {}

    // ── Audio hochladen (family_member) ──────────────────────────────────────

    #[Route('/api/lessons/{lessonId}/homework-audio', methods: ['POST'])]
    public function upload(
        int $lessonId,
        Request $request,
        LessonRepository $lessonRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTeacher()) {
            return $this->json(['error' => 'Teachers cannot upload homework audio.'], Response::HTTP_FORBIDDEN);
        }

        $family = $user->getFamily();
        if (!$family) {
            return $this->json(['error' => 'No family assigned.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $lessonRepo->find($lessonId);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], Response::HTTP_NOT_FOUND);
        }

        $hwType = trim($request->request->get('homework_type', ''));
        if (!in_array($hwType, self::AUDIO_TYPES, true)) {
            return $this->json(['error' => 'Invalid or missing homework_type. Must be one of: lesen, sonstiges.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $assignedTypes = $lesson->getHomeworkTypes() ?? [];
        if (!in_array($hwType, $assignedTypes, true)) {
            return $this->json(['error' => 'This homework type is not assigned for this lesson.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->files->get('audio');
        if (!$file) {
            return $this->json(['error' => 'No audio file provided.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->json(['error' => 'Invalid file type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['error' => 'File too large (max 50 MB).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_dir($this->audioDir)) {
            if (!@mkdir($this->audioDir, 0755, true) && !is_dir($this->audioDir)) {
                return $this->json(['error' => 'Server-Fehler: Verzeichnis konnte nicht erstellt werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $fileSize = $file->getSize();
        $ext      = $file->guessExtension() ?? 'webm';
        $filename = sprintf('%s.%s', bin2hex(random_bytes(8)), $ext);

        try {
            $file->move($this->audioDir, $filename);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Server-Fehler: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $audio = new HomeworkAudio();
        $audio->setLesson($lesson)
              ->setFamily($family)
              ->setHomeworkType($hwType)
              ->setFilename($filename)
              ->setMimeType($mimeType)
              ->setFileSize($fileSize);

        $em->persist($audio);
        $em->flush();

        return $this->json($this->serialize($audio), Response::HTTP_CREATED);
    }

    // ── Audio streamen (auth-geschützt) ──────────────────────────────────────

    #[Route('/api/homework-audio/{id}/stream', methods: ['GET'])]
    public function serve(int $id, HomeworkAudioRepository $repo): Response
    {
        /** @var User $user */
        $user  = $this->getUser();
        $audio = $repo->find($id);

        if (!$audio) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccess($user, $audio)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->audioDir . '/' . $audio->getFilename();
        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path, 200, ['Content-Type' => $audio->getMimeType()]);
    }

    // ── Audio löschen (eigene Familie oder Lehrerin) ─────────────────────────

    #[Route('/api/homework-audio/{id}/delete', methods: ['POST'])]
    public function delete(int $id, HomeworkAudioRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $audio = $repo->find($id);

        if (!$audio) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccess($user, $audio)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->audioDir . '/' . $audio->getFilename();
        if (file_exists($path)) {
            try { unlink($path); } catch (\Throwable) {}
        }

        $em->remove($audio);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function canAccess(User $user, HomeworkAudio $audio): bool
    {
        if ($user->isTeacher()) return true;
        $family = $user->getFamily();
        if ($family === null) return false;
        $fid = $family->getId();
        return $fid !== null && $fid === $audio->getFamily()->getId();
    }

    private function serialize(HomeworkAudio $a): array
    {
        return [
            'id'           => $a->getId(),
            'homeworkType' => $a->getHomeworkType(),
            'mimeType'     => $a->getMimeType(),
            'fileSize'     => $a->getFileSize(),
            'uploadedAt'   => $a->getUploadedAt()->format(\DateTimeInterface::ATOM),
            'familyId'     => $a->getFamily()->getId(),
            'familyName'   => $a->getFamily()->getName(),
        ];
    }
}
