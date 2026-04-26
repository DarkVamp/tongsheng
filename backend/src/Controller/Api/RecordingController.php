<?php

namespace App\Controller\Api;

use App\Entity\Recording;
use App\Entity\User;
use App\Repository\RecordingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/recordings')]
class RecordingController extends AbstractController
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
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB
    private const DELETE_AFTER_WEEKS = 5;

    public function __construct(
        #[Autowire('%app.recordings_dir%')]
        private string $recordingsDir
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(RecordingRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTeacher()) {
            $recordings = $repo->findBy([], ['recordedAt' => 'DESC']);
        } else {
            $recordings = $repo->findBy(['user' => $user], ['recordedAt' => 'DESC']);
        }

        return $this->json(array_map(fn(Recording $r) => $this->serialize($r, $user->isTeacher()), $recordings));
    }

    #[Route('', methods: ['POST'])]
    public function upload(
        Request $request,
        RecordingRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTeacher()) {
            return $this->json(['error' => 'Teachers cannot upload recordings.'], Response::HTTP_FORBIDDEN);
        }

        if ($repo->hasUploadedToday($user->getId())) {
            return $this->json(['error' => 'You have already uploaded a recording today.'], Response::HTTP_CONFLICT);
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

        if (!is_dir($this->recordingsDir)) {
            if (!mkdir($this->recordingsDir, 0755, true) && !is_dir($this->recordingsDir)) {
                return $this->json(['error' => 'Server-Fehler: Aufnahmeverzeichnis konnte nicht erstellt werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if (!is_writable($this->recordingsDir)) {
            return $this->json(['error' => 'Server-Fehler: Aufnahmeverzeichnis nicht beschreibbar.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileSize = $file->getSize();
        $filename = sprintf('%d_%s.%s', $user->getId(), bin2hex(random_bytes(8)), $file->guessExtension() ?? 'bin');
        try {
            $file->move($this->recordingsDir, $filename);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Server-Fehler: Datei konnte nicht gespeichert werden. (' . $e->getMessage() . ')'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $recording = new Recording();
        $recording->setUser($user)
            ->setFilename($filename)
            ->setMimeType($mimeType)
            ->setFileSize($fileSize)
            ->setDeleteAt(new \DateTimeImmutable('+' . self::DELETE_AFTER_WEEKS . ' weeks'));

        $em->persist($recording);
        $em->flush();

        return $this->json($this->serialize($recording, false), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[Route('/{id}/delete', methods: ['POST'])]
    public function delete(int $id, RecordingRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $recording = $repo->find($id);

        if (!$recording) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isTeacher() && $recording->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->recordingsDir . '/' . $recording->getFilename();
        if (file_exists($path)) {
            try { unlink($path); } catch (\Throwable) {}
        }

        $em->remove($recording);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/audio', methods: ['GET'])]
    public function audio(int $id, RecordingRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $recording = $repo->find($id);

        if (!$recording) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isTeacher() && $recording->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $path = $this->recordingsDir . '/' . $recording->getFilename();
        if (!file_exists($path)) {
            return $this->json(['error' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($path, 200, ['Content-Type' => $recording->getMimeType()]);
    }

    private function serialize(Recording $r, bool $includeFamily): array
    {
        $data = [
            'id'          => $r->getId(),
            'recordedAt'  => $r->getRecordedAt()->format(\DateTimeInterface::ATOM),
            'deleteAt'    => $r->getDeleteAt()?->format(\DateTimeInterface::ATOM),
            'commentCount' => $r->getComments()->count(),
        ];

        if ($includeFamily) {
            $data['family'] = $r->getUser()->getFamilyName();
            $data['familyId'] = $r->getUser()->getId();
        }

        return $data;
    }
}
