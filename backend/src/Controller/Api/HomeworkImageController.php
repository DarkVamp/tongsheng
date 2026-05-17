<?php

namespace App\Controller\Api;

use App\Entity\HomeworkAudio;
use App\Entity\HomeworkImage;
use App\Entity\User;
use App\Repository\FamilyRepository;
use App\Repository\HomeworkAudioRepository;
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
    private const PHOTO_TYPES   = ['schreiben', 'schriftlich', 'malen', 'sonstiges'];

    public function __construct(
        #[Autowire('%app.homework_dir%')]
        private string $homeworkDir
    ) {}

    // ── Letzte Stunde mit Hausaufgaben + eigene Bilder ───────────────────────

    #[Route('/api/lessons/latest-homework', methods: ['GET'])]
    public function latestHomework(
        LessonRepository $lessonRepo,
        HomeworkImageRepository $imageRepo,
        HomeworkAudioRepository $audioRepo,
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
        $audio  = $audioRepo->findByLessonAndFamily($lesson, $family);

        return $this->json([
            'lesson' => $lessonData,
            'images' => array_map($this->serializeImage(...), $images),
            'audio'  => array_map($this->serializeAudio(...), $audio),
        ]);
    }

    // ── Eigene Einreichungen einer Stunde abrufen (family_member) ───────────

    #[Route('/api/lessons/{lessonId}/family-homework', methods: ['GET'])]
    public function familyHomework(
        int $lessonId,
        LessonRepository $lessonRepo,
        HomeworkImageRepository $imageRepo,
        HomeworkAudioRepository $audioRepo
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $family = $user->getFamily();
        if (!$family) {
            return $this->json(['error' => 'No family assigned.'], Response::HTTP_FORBIDDEN);
        }

        $lesson = $lessonRepo->find($lessonId);
        if (!$lesson) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $images = $imageRepo->findByLessonAndFamily($lesson, $family);
        $audio  = $audioRepo->findByLessonAndFamily($lesson, $family);

        return $this->json([
            'images' => array_map($this->serializeImage(...), $images),
            'audio'  => array_map($this->serializeAudio(...), $audio),
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

        $hwType = trim($request->request->get('homework_type', ''));
        if (!in_array($hwType, self::PHOTO_TYPES, true)) {
            return $this->json(['error' => 'Invalid or missing homework_type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $assignedTypes = $lesson->getHomeworkTypes() ?? [];
        if (!in_array($hwType, $assignedTypes, true)) {
            return $this->json(['error' => 'This homework type is not assigned for this lesson.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
              ->setHomeworkType($hwType)
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

    // ── Einreichungen nach Typ gruppiert (nur Lehrerin) ──────────────────────

    #[Route('/api/lessons/{lessonId}/homework/by-type', methods: ['GET'])]
    public function byType(
        int $lessonId,
        LessonRepository $lessonRepo,
        HomeworkImageRepository $imageRepo,
        HomeworkAudioRepository $audioRepo,
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

        $types       = $lesson->getHomeworkTypes() ?? [];
        $allFamilies = $familyRepo->findBy([], ['name' => 'ASC']);
        $allImages   = $imageRepo->findByLesson($lesson);
        $allAudio    = $audioRepo->findByLesson($lesson);

        // Index by [type][familyId]
        $imagesByTypeFam = [];
        foreach ($allImages as $img) {
            $imagesByTypeFam[$img->getHomeworkType()][$img->getFamily()->getId()][] = $this->serializeImage($img);
        }

        $audioByTypeFam = [];
        foreach ($allAudio as $a) {
            $audioByTypeFam[$a->getHomeworkType()][$a->getFamily()->getId()][] = $this->serializeAudio($a);
        }

        $byType = [];
        foreach ($types as $type) {
            $families = array_map(function ($f) use ($type, $imagesByTypeFam, $audioByTypeFam) {
                $fid    = $f->getId();
                $images = $imagesByTypeFam[$type][$fid] ?? [];
                $audio  = $audioByTypeFam[$type][$fid] ?? [];
                return [
                    'id'        => $fid,
                    'name'      => $f->getName(),
                    'images'    => $images,
                    'audio'     => $audio,
                    'submitted' => !empty($images) || !empty($audio),
                ];
            }, $allFamilies);

            $byType[$type] = ['families' => $families];
        }

        return $this->json([
            'lesson' => $this->serializeLesson($lesson),
            'byType' => $byType,
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
            'homeworkTypes'    => $l->getHomeworkTypes() ?? [],
        ];
    }

    private function serializeImage(HomeworkImage $img): array
    {
        return [
            'id'               => $img->getId(),
            'homeworkType'     => $img->getHomeworkType(),
            'originalFilename' => $img->getOriginalFilename(),
            'mimeType'         => $img->getMimeType(),
            'uploadedAt'       => $img->getUploadedAt()->format(\DateTimeInterface::ATOM),
            'familyId'         => $img->getFamily()->getId(),
            'familyName'       => $img->getFamily()->getName(),
        ];
    }

    private function serializeAudio(HomeworkAudio $a): array
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
