<?php

namespace App\Repository;

use App\Entity\FeedbackMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FeedbackMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedbackMessage::class);
    }

    /** @return FeedbackMessage[] */
    public function findByLesson(int $lessonId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.lesson = :lesson')
            ->setParameter('lesson', $lessonId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return FeedbackMessage[] */
    public function findByLessonAndFamily(int $lessonId, int $familyId): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.student', 's')
            ->join('s.family', 'f')
            ->where('m.lesson = :lesson')
            ->andWhere('f.id = :family')
            ->setParameter('lesson', $lessonId)
            ->setParameter('family', $familyId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
