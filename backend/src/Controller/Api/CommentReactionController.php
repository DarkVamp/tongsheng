<?php

namespace App\Controller\Api;

use App\Entity\Comment;
use App\Entity\CommentReaction;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\CommentReactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/comments/{commentId}')]
class CommentReactionController extends AbstractController
{
    private const VALID_TYPES = ['thumbs_up', 'heart', 'thumbs_down'];

    #[Route('/react', methods: ['POST'])]
    public function react(
        int $commentId,
        Request $request,
        CommentRepository $commentRepo,
        CommentReactionRepository $reactionRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $comment = $commentRepo->find($commentId);

        if (!$comment) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$user->canAccessRecording($comment->getRecording())) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $type = $data['type'] ?? '';

        if (!in_array($type, self::VALID_TYPES, true)) {
            return $this->json(['error' => 'Invalid reaction type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $reactionRepo->findOneBy(['comment' => $comment, 'user' => $user]);

        if ($existing) {
            if ($existing->getType() === $type) {
                $em->remove($existing);
            } else {
                $existing->setType($type);
            }
        } else {
            $reaction = new CommentReaction();
            $reaction->setComment($comment)->setUser($user)->setType($type);
            $em->persist($reaction);
        }

        $em->flush();

        $reactions = $reactionRepo->findBy(['comment' => $comment]);
        $counts = ['thumbs_up' => 0, 'heart' => 0, 'thumbs_down' => 0];
        $users  = ['thumbs_up' => [], 'heart' => [], 'thumbs_down' => []];
        $mine = null;
        foreach ($reactions as $r) {
            $type = $r->getType();
            $counts[$type]++;
            $users[$type][] = $r->getUser()->getFamilyName();
            if ($r->getUser()->getId() === $user->getId()) {
                $mine = $type;
            }
        }

        return $this->json(array_merge($counts, ['mine' => $mine, 'users' => $users]));
    }
}
