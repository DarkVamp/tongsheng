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

        if (!$user->canAccessRecording($recording)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(array_map(
            fn(Comment $c) => $this->serializeComment($c, $user),
            $recording->getComments()->toArray()
        ));
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

        $recording = $repo->find($recordingId);
        if (!$recording) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$user->canAccessRecording($recording)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        if ($content === '') {
            return $this->json(['error' => 'Comment cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comment = new Comment();
        $comment->setRecording($recording)->setContent($content)->setAuthor($user);

        $em->persist($comment);
        $em->flush();

        return $this->json($this->serializeComment($comment, $user), Response::HTTP_CREATED);
    }

    private function serializeComment(Comment $c, User $currentUser): array
    {
        $author = $c->getAuthor();
        $counts = ['thumbs_up' => 0, 'heart' => 0, 'thumbs_down' => 0];
        $users  = ['thumbs_up' => [], 'heart' => [], 'thumbs_down' => []];
        $mine = null;
        foreach ($c->getReactions() as $r) {
            $type = $r->getType();
            $counts[$type]++;
            $users[$type][] = $r->getUser()->getFamilyName();
            if ($r->getUser()->getId() === $currentUser->getId()) {
                $mine = $type;
            }
        }

        return [
            'id'         => $c->getId(),
            'content'    => $c->getContent(),
            'createdAt'  => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'authorName' => $author?->getFamilyName(),
            'authorRole' => $author?->getRole(),
            'reactions'  => array_merge($counts, ['mine' => $mine, 'users' => $users]),
        ];
    }
}
