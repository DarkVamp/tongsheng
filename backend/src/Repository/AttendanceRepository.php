<?php

namespace App\Repository;

use App\Entity\Attendance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    public function findByLessonAndStudent(int $lessonId, int $studentId): ?Attendance
    {
        return $this->createQueryBuilder('a')
            ->where('a.lesson = :lessonId')
            ->andWhere('a.student = :studentId')
            ->setParameter('lessonId', $lessonId)
            ->setParameter('studentId', $studentId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
