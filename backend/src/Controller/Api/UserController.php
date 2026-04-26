<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me')]
class UserController extends AbstractController
{
    #[Route('/locale', methods: ['PATCH'])]
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
}
