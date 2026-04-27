<?php

namespace App\Repository;

use App\Entity\Recording;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recording>
 */
class RecordingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recording::class);
    }

    public function hasUploadedToday(int $userId): bool
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :userId')
            ->andWhere('r.recordedAt >= :today')
            ->andWhere('r.recordedAt < :tomorrow')
            ->setParameter('userId', $userId)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return Recording[]
     */
    public function findByFamily(int $familyId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->where('u.family = :familyId')
            ->setParameter('familyId', $familyId)
            ->orderBy('r.recordedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Recording[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.deleteAt IS NOT NULL')
            ->andWhere('r.deleteAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
