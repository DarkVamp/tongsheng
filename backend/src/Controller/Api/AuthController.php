<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    #[Route('/login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = bin2hex(random_bytes(32));
        $user->setApiToken($token);
        $em->flush();

        return $this->json([
            'token'  => $token,
            'role'   => $user->getRole(),
            'name'   => $user->getFamilyName(),
            'locale' => $user->getLocale(),
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setApiToken(null);
        $em->flush();

        return $this->json(['message' => 'Logged out.']);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'     => $user->getId(),
            'name'   => $user->getFamilyName(),
            'role'   => $user->getRole(),
            'locale' => $user->getLocale(),
        ]);
    }
}
