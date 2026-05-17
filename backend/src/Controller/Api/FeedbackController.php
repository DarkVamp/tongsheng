<?php

namespace App\Controller\Api;

use App\Entity\FeedbackAttachment;
use App\Entity\FeedbackMessage;
use App\Entity\User;
use App\Repository\FeedbackAttachmentRepository;
use App\Repository\FeedbackMessageRepository;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FeedbackController extends AbstractController
{
    private const ALLOWED_AUDIO_TYPES = [
        'audio/mpeg', 'audio/mp3',
        'audio/mp4', 'audio/x-m4a', 'audio/aac',
        'audio/webm', 'video/webm',
        'audio/ogg', 'audio/vorbis',
        'audio/wav', 'audio/x-wav', 'audio/wave',
        'video/mp4', 'audio/3gpp', 'video/3gpp',
    ];
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    public function __construct(
        #[Autowire('%app.feedback_dir%')]
        private string $feedbackDir
    ) {}

    // ── Feedback einer Stunde abrufen ────────────────────────────────────────

    #[Route('/api/lessons/{id}/feedback', methods: ['GET'])]
    public function listForLesson(
        int $id,
        LessonRepository $lessonRepo,
        FeedbackMessageRepository $msgRepo
    ): JsonResponse {
        /** @var User $user */
        $user   = $this->getUser();
        $lesson = $lessonRepo->find($id);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($user->isTeacher()) {
            $messages = $msgRepo->findByLesson($id);
        } else {
            $family = $user->getFamily();
            if (!$family) {
                return $this->json([], Response::HTTP_OK);
            }
            $messages = $msgRepo->findByLessonAndFamily($id, $family->getId());
        }

        return $this->json(array_map(fn(FeedbackMessage $m) => $this->serializeMessage($m), $messages));
    }

    // ── Neue Message anlegen (Lehrerin) ──────────────────────────────────────

    #[Route('/api/lessons/{lessonId}/feedback/{studentId}', methods: ['POST'])]
    public function create(
        int $lessonId,
        int $studentId,
        Request $request,
        LessonRepository $lessonRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $lessonRepo->find($lessonId);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], Response::HTTP_NOT_FOUND);
        }

        $student = $userRepo->find($studentId);
        if (!$student || !$student->isStudent()) {
            return $this->json(['error' => 'Student not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $text = trim($data['text'] ?? '') ?: null;

        $message = new FeedbackMessage();
        $message->setLesson($lesson)
                ->setStudent($student)
                ->setAuthor($user)
                ->setText($text);

        $em->persist($message);
        $em->flush();

        return $this->json($this->serializeMessage($message), Response::HTTP_CREATED);
    }

    // ── Message bearbeiten (Text, Lehrerin) ──────────────────────────────────

    #[Route('/api/feedback/messages/{id}', methods: ['PATCH'])]
    public function patch(
        int $id,
        Request $request,
        FeedbackMessageRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user    = $this->getUser();
        $message = $repo->find($id);
        if (!$message) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('text', $data)) {
            $message->setText(trim($data['text'] ?? '') ?: null);
        }

        $em->flush();

        return $this->json($this->serializeMessage($message));
    }

    // ── Anhang hochladen (Lehrerin) ──────────────────────────────────────────

    #[Route('/api/feedback/messages/{id}/attachment', methods: ['POST'])]
    public function uploadAttachment(
        int $id,
        Request $request,
        FeedbackMessageRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user    = $this->getUser();
        $message = $repo->find($id);
        if (!$message) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
            $type = 'image';
        } elseif (in_array($mimeType, self::ALLOWED_AUDIO_TYPES, true)) {
            $type = 'audio';
        } else {
            return $this->json(['error' => 'Invalid file type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['error' => 'File too large (max 50 MB).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_dir($this->feedbackDir)) {
            if (!@mkdir($this->feedbackDir, 0755, true) && !is_dir($this->feedbackDir)) {
                return $this->json(['error' => 'Server-Fehler: Verzeichnis konnte nicht erstellt werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $ext      = $file->guessExtension() ?? ($type === 'audio' ? 'webm' : 'jpg');
        $filename = sprintf('%s.%s', bin2hex(random_bytes(8)), $ext);
        $fileSize = $file->getSize();

        try {
            $file->move($this->feedbackDir, $filename);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Server-Fehler: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $attachment = new FeedbackAttachment();
        $attachment->setMessage($message)
                   ->setType($type)
                   ->setFilename($filename)
                   ->setMimeType($mimeType)
                   ->setFileSize($fileSize);

        $em->persist($attachment);
        $em->flush();

        return $this->json($this->serializeAttachment($attachment), Response::HTTP_CREATED);
    }

    // ── Anhang abrufen (auth) ────────────────────────────────────────────────

    #[Route('/api/feedback/attachments/{id}/stream', methods: ['GET'])]
    public function serveAttachment(int $id, FeedbackAttachmentRepository $repo): Response
    {
        /** @var User $user */
        $user       = $this->getUser();
        $attachment = $repo->find($id);
        if (!$attachment) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessMessage($user, $attachment->getMessage())) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->feedbackDir . '/' . $attachment->getFilename();
        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path, 200, ['Content-Type' => $attachment->getMimeType()]);
    }

    // ── Message löschen (Lehrerin) ───────────────────────────────────────────

    #[Route('/api/feedback/messages/{id}/delete', methods: ['POST'])]
    public function deleteMessage(
        int $id,
        FeedbackMessageRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user    = $this->getUser();
        $message = $repo->find($id);
        if (!$message) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        foreach ($message->getAttachments() as $att) {
            $path = $this->feedbackDir . '/' . $att->getFilename();
            if (file_exists($path)) {
                try { unlink($path); } catch (\Throwable) {}
            }
        }

        $em->remove($message);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Anhang löschen (Lehrerin) ────────────────────────────────────────────

    #[Route('/api/feedback/attachments/{id}/delete', methods: ['POST'])]
    public function deleteAttachment(
        int $id,
        FeedbackAttachmentRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user       = $this->getUser();
        $attachment = $repo->find($id);
        if (!$attachment) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->feedbackDir . '/' . $attachment->getFilename();
        if (file_exists($path)) {
            try { unlink($path); } catch (\Throwable) {}
        }

        $em->remove($attachment);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function canAccessMessage(User $user, FeedbackMessage $message): bool
    {
        if ($user->isTeacher()) return true;
        $family = $user->getFamily();
        if ($family === null) return false;
        $studentFamily = $message->getStudent()->getFamily();
        if ($studentFamily === null) return false;
        return $family->getId() !== null && $family->getId() === $studentFamily->getId();
    }

    private function serializeMessage(FeedbackMessage $m): array
    {
        return [
            'id'          => $m->getId(),
            'lessonId'    => $m->getLesson()->getId(),
            'studentId'   => $m->getStudent()->getId(),
            'studentName' => $m->getStudent()->getFamilyName(),
            'familyId'    => $m->getStudent()->getFamily()?->getId(),
            'familyName'  => $m->getStudent()->getFamily()?->getName(),
            'text'        => $m->getText(),
            'createdAt'   => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'attachments' => array_map(
                fn(FeedbackAttachment $a) => $this->serializeAttachment($a),
                $m->getAttachments()->toArray()
            ),
        ];
    }

    private function serializeAttachment(FeedbackAttachment $a): array
    {
        return [
            'id'        => $a->getId(),
            'type'      => $a->getType(),
            'mimeType'  => $a->getMimeType(),
            'fileSize'  => $a->getFileSize(),
            'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
