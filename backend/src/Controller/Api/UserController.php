<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/api/me/locale', methods: ['PATCH'])]
    public function updateLocale(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $locale = $data['locale'] ?? '';

        if (!in_array($locale, ['de', 'zh'], true)) {
            return $this->json(['error' => 'Invalid locale.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setLocale($locale);
        $em->flush();

        return $this->json(['locale' => $locale]);
    }

    #[Route('/api/users/{id}/toggle-student', methods: ['POST'])]
    public function toggleStudent(int $id, UserRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $teacher */
        $teacher = $this->getUser();
        if (!$teacher->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $user = $repo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($user->isTeacher()) {
            return $this->json(['error' => 'Cannot mark teacher as student.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setIsStudent(!$user->isStudent());
        $em->flush();

        return $this->json(['isStudent' => $user->isStudent()]);
    }
}
