<?php

namespace App\Controller\Api;

use App\Entity\Comment;
use App\Entity\User;
use App\Repository\RecordingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/recordings/{recordingId}/comments')]
class CommentController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(int $recordingId, RecordingRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $recording = $repo->find($recordingId);

        if (!$recording) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isTeacher() && $recording->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(array_map(fn(Comment $c) => [
            'id'        => $c->getId(),
            'content'   => $c->getContent(),
            'createdAt' => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $recording->getComments()->toArray()));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        int $recordingId,
        Request $request,
        RecordingRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Only teachers can comment.'], Response::HTTP_FORBIDDEN);
        }

        $recording = $repo->find($recordingId);
        if (!$recording) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        if ($content === '') {
            return $this->json(['error' => 'Comment cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comment = new Comment();
        $comment->setRecording($recording)->setContent($content);

        $em->persist($comment);
        $em->flush();

        return $this->json([
            'id'        => $comment->getId(),
            'content'   => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
