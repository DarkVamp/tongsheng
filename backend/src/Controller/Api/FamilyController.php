<?php

namespace App\Controller\Api;

use App\Entity\Family;
use App\Entity\User;
use App\Repository\FamilyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/families')]
class FamilyController extends AbstractController
{
    public function __construct(
        #[Autowire('%app.recordings_dir%')]
        private string $recordingsDir
    ) {}

    // ── Alle Familien abrufen ────────────────────────────────────────────────

    #[Route('', methods: ['GET'])]
    public function list(FamilyRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $families = $repo->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn(Family $f) => [
            'id'   => $f->getId(),
            'name' => $f->getName(),
        ], $families));
    }

    // ── Neue Familie anlegen ─────────────────────────────────────────────────

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            return $this->json(['error' => 'Name darf nicht leer sein.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $family = new Family();
        $family->setName($name);

        $em->persist($family);
        $em->flush();

        return $this->json(['id' => $family->getId(), 'name' => $family->getName()], Response::HTTP_CREATED);
    }

    // ── Familie löschen (löscht alle zugehörigen User + Aufnahmen) ───────────

    #[Route('/{id}/delete', methods: ['POST'])]
    public function delete(int $id, FamilyRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $family = $repo->find($id);
        if (!$family) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        // Audiodateien aller Familienmitglieder vom Server löschen
        foreach ($family->getMembers() as $member) {
            $userDir = $this->recordingsDir . '/' . $member->getId();
            if (is_dir($userDir)) {
                foreach (array_diff(scandir($userDir), ['.', '..']) as $file) {
                    @unlink($userDir . '/' . $file);
                }
                @rmdir($userDir);
            }
        }

        $em->remove($family);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Familien mit Mitgliedern (für Schüler-Tab) ───────────────────────────

    #[Route('/members', methods: ['GET'])]
    public function members(FamilyRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isTeacher()) {
            return $this->json(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $families = $repo->findBy([], ['name' => 'ASC']);

        $result = [];
        foreach ($families as $family) {
            $members = $family->getMembers()->toArray();
            usort($members, fn(User $a, User $b) => strcmp($a->getFamilyName(), $b->getFamilyName()));

            $result[] = [
                'familyId'   => $family->getId(),
                'familyName' => $family->getName(),
                'members'    => array_map(fn(User $m) => [
                    'id'        => $m->getId(),
                    'name'      => $m->getFamilyName(),
                    'isStudent' => $m->isStudent(),
                ], $members),
            ];
        }

        return $this->json($result);
    }
}
