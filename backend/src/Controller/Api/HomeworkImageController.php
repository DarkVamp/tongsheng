<?php

namespace App\Controller\Api;

use App\Entity\HomeworkImage;
use App\Entity\User;
use App\Repository\FamilyRepository;
use App\Repository\HomeworkImageRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeworkImageController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/jpg', 'image/png',
        'image/gif', 'image/webp', 'image/heic', 'image/heif',
    ];
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 MB

    public function __construct(
        #[Autowire('%app.homework_dir%')]
        private string $homeworkDir
    ) {}

    // ── Letzte Stunde mit Hausaufgaben + eigene Bilder ───────────────────────
    // Wird von Familie UND Lehrerin genutzt (unterschiedliche Sicht)

    #[Route('/api/lessons/latest-homework', methods: ['GET'])]
    public function latestHomework(
        LessonRepository $lessonRepo,
        HomeworkImageRepository $imageRepo,
        FamilyRepository $familyRepo
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $lesson = $lessonRepo->findLatestWithHomework();
        if (!$lesson) {
            return $this->json(null);
        }

        $lessonData = $this->serializeLesson($lesson);

        if ($user->isTeacher()) {
            $allFamilies = $familyRepo->findBy([], ['name' => 'ASC']);
            $allImages   = $imageRepo->findByLesson($lesson);

            $imagesByFamily = [];
            foreach ($allImages as $img) {
                $imagesByFamily[$img->getFamily()->getId()][] = $this->serializeImage($img);
            }

            return $this->json([
                'lesson'   => $lessonData,
                'families' => array_map(fn($f) => [
                    'id'        => $f->getId(),
                    'name'      => $f->getName(),
                    'images'    => $imagesByFamily[$f->getId()] ?? [],
                    'submitted' => isset($imagesByFamily[$f->getId()]),
                ], $allFamilies),
            ]);
        }

        $family = $user->getFamily();
        if (!$family) {
            return $this->json(['error' => 'No family assigned.'], Response::HTTP_FORBIDDEN);
        }

        $images = $imageRepo->findByLessonAndFamily($lesson, $family);

        return $this->json([
            'lesson' => $lessonData,
            'images' => array_map($this->serializeImage(...), $images),
        ]);
    }

    // ── Bild hochladen (family_member, für eigene Familie) ───────────────────

    #[Route('/api/lessons/{lessonId}/homework', methods: ['POST'])]
    public function upload(
        int $lessonId,
        Request $request,
        LessonRepository $lessonRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTeacher()) {
            return $this->json(['error' => 'Teachers cannot upload homework images.'], Response::HTTP_FORBIDDEN);
        }

        $family = $user->getFamily();
        if (!$family) {
            return $this->json(['error' => 'No family assigned.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $lessonRepo->find($lessonId);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$lesson->isHomeworkAssigned()) {
            return $this->json(['error' => 'No homework assigned for this lesson.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['error' => 'No image file provided.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->json(['error' => 'Invalid file type. Nur Bilder erlaubt.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['error' => 'File too large (max 20 MB).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_dir($this->homeworkDir)) {
            if (!@mkdir($this->homeworkDir, 0755, true) && !is_dir($this->homeworkDir)) {
                return $this->json(['error' => 'Server-Fehler: Verzeichnis konnte nicht erstellt werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $ext      = $file->guessExtension() ?? 'jpg';
        $filename = sprintf('%s.%s', bin2hex(random_bytes(8)), $ext);

        try {
            $file->move($this->homeworkDir, $filename);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Server-Fehler: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $image = new HomeworkImage();
        $image->setLesson($lesson)
              ->setFamily($family)
              ->setFilePath($filename)
              ->setOriginalFilename($file->getClientOriginalName() ?: $filename)
              ->setMimeType($mimeType);

        $em->persist($image);
        $em->flush();

        return $this->json($this->serializeImage($image), Response::HTTP_CREATED);
    }

    // ── Bild abrufen (auth-geschützt) ────────────────────────────────────────

    #[Route('/api/homework/{id}/image', methods: ['GET'])]
    public function image(int $id, HomeworkImageRepository $repo): Response
    {
        /** @var User $user */
        $user  = $this->getUser();
        $image = $repo->find($id);

        if (!$image) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccessImage($user, $image)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->homeworkDir . '/' . $image->getFilePath();
        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path, 200, ['Content-Type' => $image->getMimeType()]);
    }

    // ── Bild löschen (eigene Familie oder Lehrerin) ──────────────────────────

    #[Route('/api/homework/{id}/delete', methods: ['POST'])]
    public function delete(int $id, HomeworkImageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $image = $repo->find($id);

        if (!$image) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canDeleteImage($user, $image)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->homeworkDir . '/' . $image->getFilePath();
        if (file_exists($path)) {
            try { unlink($path); } catch (\Throwable) {}
        }

        $em->remove($image);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Alle Familien-Einreichungen zu einer Stunde (nur Lehrerin) ───────────

    #[Route('/api/lessons/{lessonId}/homework/all', methods: ['GET'])]
    public function allForLesson(
        int $lessonId,
        LessonRepository $lessonRepo,
        HomeworkImageRepository $imageRepo,
        FamilyRepository $familyRepo
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

        $allFamilies = $familyRepo->findBy([], ['name' => 'ASC']);
        $allImages   = $imageRepo->findByLesson($lesson);

        $imagesByFamily = [];
        foreach ($allImages as $img) {
            $imagesByFamily[$img->getFamily()->getId()][] = $this->serializeImage($img);
        }

        return $this->json([
            'lesson'   => $this->serializeLesson($lesson),
            'families' => array_map(fn($f) => [
                'id'        => $f->getId(),
                'name'      => $f->getName(),
                'images'    => $imagesByFamily[$f->getId()] ?? [],
                'submitted' => isset($imagesByFamily[$f->getId()]),
            ], $allFamilies),
        ]);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function canAccessImage(User $user, HomeworkImage $image): bool
    {
        if ($user->isTeacher()) return true;
        $family = $user->getFamily();
        if ($family === null) return false;
        $fid = $family->getId();
        return $fid !== null && $fid === $image->getFamily()->getId();
    }

    private function canDeleteImage(User $user, HomeworkImage $image): bool
    {
        return $this->canAccessImage($user, $image);
    }

    private function serializeLesson(\App\Entity\Lesson $l): array
    {
        return [
            'id'               => $l->getId(),
            'date'             => $l->getDate()->format('Y-m-d'),
            'title'            => $l->getTitle(),
            'homeworkAssigned' => $l->isHomeworkAssigned(),
        ];
    }

    private function serializeImage(HomeworkImage $img): array
    {
        return [
            'id'               => $img->getId(),
            'originalFilename' => $img->getOriginalFilename(),
            'mimeType'         => $img->getMimeType(),
            'uploadedAt'       => $img->getUploadedAt()->format(\DateTimeInterface::ATOM),
            'familyId'         => $img->getFamily()->getId(),
            'familyName'       => $img->getFamily()->getName(),
        ];
    }
}
