<?php

namespace App\Controller\Api;

use App\Entity\Invitation;
use App\Entity\User;
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
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'family';
        $familyGroupId = $data['familyGroupId'] ?? null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Ungültige E-Mail-Adresse.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!in_array($role, ['family', 'family_member'], true)) {
            return $this->json(['error' => 'Ungültige Rolle.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($role === 'family_member') {
            if (!$familyGroupId) {
                return $this->json(['error' => 'Für Familienmitglieder muss eine Familie gewählt werden.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Prüfen ob die Familie existiert
            $familyUser = $userRepo->findOneBy(['familyGroupId' => $familyGroupId, 'role' => 'family']);
            if (!$familyUser) {
                return $this->json(['error' => 'Familie nicht gefunden.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Bereits eingeladen oder registriert?
        if ($userRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Diese E-Mail ist bereits registriert.'], Response::HTTP_CONFLICT);
        }
        if ($invRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Für diese E-Mail gibt es bereits eine ausstehende Einladung.'], Response::HTTP_CONFLICT);
        }

        $invitation = new Invitation();
        $invitation->setEmail($email)
            ->setRole($role)
            ->setFamilyGroupId($role === 'family_member' ? (int)$familyGroupId : null)
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

    // ── Alle Familien abrufen (für Familienmitglied-Einladung) ───────────────

    #[Route('/api/families', methods: ['GET'])]
    public function families(UserRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $families = $repo->findBy(['role' => 'family'], ['familyName' => 'ASC']);

        return $this->json(array_map(fn(User $u) => [
            'familyGroupId' => $u->getFamilyGroupId(),
            'name'          => $u->getFamilyName(),
        ], $families));
    }

    // ── Alle Familienmitglieder mit isStudent-Flag ───────────────────────────

    #[Route('/api/families/members', methods: ['GET'])]
    public function familyMembers(UserRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $families = $repo->findBy(['role' => 'family'], ['familyName' => 'ASC']);

        $result = [];
        foreach ($families as $family) {
            $members = $repo->findBy(['familyGroupId' => $family->getFamilyGroupId()]);
            usort($members, fn(User $a, User $b) => strcmp($a->getFamilyName(), $b->getFamilyName()));

            $result[] = [
                'familyGroupId' => $family->getFamilyGroupId(),
                'familyName'    => $family->getFamilyName(),
                'members'       => array_map(fn(User $m) => [
                    'id'        => $m->getId(),
                    'name'      => $m->getFamilyName(),
                    'role'      => $m->getRole(),
                    'isStudent' => $m->isStudent(),
                ], $members),
            ];
        }

        return $this->json($result);
    }

    // ── Öffentlich: Token validieren (vor der Registrierung) ─────────────────

    #[Route('/api/register/validate', methods: ['GET'])]
    public function validate(Request $request, InvitationRepository $repo, UserRepository $userRepo): JsonResponse
    {
        $token = $request->query->get('token', '');
        $invitation = $repo->findByToken($token);

        if (!$invitation || $invitation->isExpired()) {
            return $this->json(['error' => 'Einladungslink ungültig oder abgelaufen.'], Response::HTTP_NOT_FOUND);
        }

        $familyName = null;
        if ($invitation->getRole() === 'family_member' && $invitation->getFamilyGroupId()) {
            $familyUser = $userRepo->find($invitation->getFamilyGroupId());
            $familyName = $familyUser?->getFamilyName();
        }

        return $this->json([
            'email'      => $invitation->getEmail(),
            'role'       => $invitation->getRole(),
            'familyName' => $familyName,
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
        $data = json_decode($request->getContent(), true);
        $token = trim($data['token'] ?? '');
        $familyName = trim($data['familyName'] ?? '');
        $password = $data['password'] ?? '';

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
            ->setRole($invitation->getRole())
            ->setPassword($hasher->hashPassword($newUser, $password));

        $em->persist($newUser);
        $em->flush();

        // family_group_id setzen: Bei 'family' = eigene id, bei 'family_member' = aus Einladung
        if ($invitation->getRole() === 'family') {
            $newUser->setFamilyGroupId($newUser->getId());
        } else {
            $newUser->setFamilyGroupId($invitation->getFamilyGroupId());
        }

        // Direkt einloggen: API-Token ausstellen
        $apiToken = bin2hex(random_bytes(32));
        $newUser->setApiToken($apiToken);

        $em->remove($invitation);
        $em->flush();

        return $this->json([
            'token'         => $apiToken,
            'id'            => $newUser->getId(),
            'role'          => $newUser->getRole(),
            'name'          => $newUser->getFamilyName(),
            'locale'        => $newUser->getLocale(),
            'familyGroupId' => $newUser->getFamilyGroupId(),
        ], Response::HTTP_CREATED);
    }

    private function serializeInvitation(Invitation $i): array
    {
        return [
            'id'            => $i->getId(),
            'email'         => $i->getEmail(),
            'role'          => $i->getRole(),
            'familyGroupId' => $i->getFamilyGroupId(),
            'token'         => $i->getToken(),
            'createdAt'     => $i->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt'     => $i->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
