<?php

namespace App\Controller\Api;

use App\Entity\Invitation;
use App\Entity\User;
use App\Repository\FamilyRepository;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class InvitationController extends AbstractController
{
    // ── Lehrerin: alle ausstehenden Einladungen abrufen ──────────────────────

    #[Route('/api/invitations', methods: ['GET'])]
    public function list(InvitationRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $invitations = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn(Invitation $i) => $this->serializeInvitation($i), $invitations));
    }

    // ── Lehrerin: neue Einladung erstellen ───────────────────────────────────

    #[Route('/api/invitations', methods: ['POST'])]
    public function create(
        Request $request,
        InvitationRepository $invRepo,
        UserRepository $userRepo,
        FamilyRepository $familyRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $email    = trim($data['email'] ?? '');
        $familyId = isset($data['familyId']) ? (int)$data['familyId'] : null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Ungültige E-Mail-Adresse.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$familyId) {
            return $this->json(['error' => 'Bitte eine Familie wählen.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $family = $familyRepo->find($familyId);
        if (!$family) {
            return $this->json(['error' => 'Familie nicht gefunden.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($userRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Diese E-Mail ist bereits registriert.'], Response::HTTP_CONFLICT);
        }
        if ($invRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Für diese E-Mail gibt es bereits eine ausstehende Einladung.'], Response::HTTP_CONFLICT);
        }

        $invitation = new Invitation();
        $invitation->setEmail($email)
            ->setRole('family_member')
            ->setFamily($family)
            ->setInvitedBy($user);

        $em->persist($invitation);
        $em->flush();

        return $this->json($this->serializeInvitation($invitation), Response::HTTP_CREATED);
    }

    // ── Lehrerin: Einladung löschen ──────────────────────────────────────────

    #[Route('/api/invitations/{id}/delete', methods: ['POST'])]
    public function delete(int $id, InvitationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $invitation = $repo->find($id);
        if (!$invitation) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($invitation);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Öffentlich: Token validieren (vor der Registrierung) ─────────────────

    #[Route('/api/register/validate', methods: ['GET'])]
    public function validate(Request $request, InvitationRepository $repo): JsonResponse
    {
        $token = $request->query->get('token', '');
        $invitation = $repo->findByToken($token);

        if (!$invitation || $invitation->isExpired()) {
            return $this->json(['error' => 'Einladungslink ungültig oder abgelaufen.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'email'      => $invitation->getEmail(),
            'role'       => $invitation->getRole(),
            'familyName' => $invitation->getFamily()?->getName(),
            'expiresAt'  => $invitation->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    // ── Öffentlich: Registrierung abschließen ────────────────────────────────

    #[Route('/api/register', methods: ['POST'])]
    public function register(
        Request $request,
        InvitationRepository $invRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data       = json_decode($request->getContent(), true);
        $token      = trim($data['token'] ?? '');
        $familyName = trim($data['familyName'] ?? '');
        $password   = $data['password'] ?? '';

        $invitation = $invRepo->findByToken($token);
        if (!$invitation || $invitation->isExpired()) {
            return $this->json(['error' => 'Einladungslink ungültig oder abgelaufen.'], Response::HTTP_NOT_FOUND);
        }

        if ($familyName === '') {
            return $this->json(['error' => 'Bitte Namen eingeben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($password) < 6) {
            return $this->json(['error' => 'Passwort muss mindestens 6 Zeichen haben.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($userRepo->findOneBy(['email' => $invitation->getEmail()])) {
            return $this->json(['error' => 'Diese E-Mail ist bereits registriert.'], Response::HTTP_CONFLICT);
        }

        $newUser = new User();
        $newUser->setEmail($invitation->getEmail())
            ->setFamilyName($familyName)
            ->setRole('family_member')
            ->setFamily($invitation->getFamily())
            ->setPassword($hasher->hashPassword($newUser, $password));

        $apiToken = bin2hex(random_bytes(32));
        $newUser->setApiToken($apiToken);

        $em->persist($newUser);
        $em->remove($invitation);
        $em->flush();

        return $this->json([
            'token'    => $apiToken,
            'id'       => $newUser->getId(),
            'role'     => $newUser->getRole(),
            'name'     => $newUser->getFamilyName(),
            'locale'   => $newUser->getLocale(),
            'familyId' => $newUser->getFamily()?->getId(),
        ], Response::HTTP_CREATED);
    }

    private function serializeInvitation(Invitation $i): array
    {
        return [
            'id'        => $i->getId(),
            'email'     => $i->getEmail(),
            'role'      => $i->getRole(),
            'familyId'  => $i->getFamily()?->getId(),
            'token'     => $i->getToken(),
            'createdAt' => $i->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $i->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
