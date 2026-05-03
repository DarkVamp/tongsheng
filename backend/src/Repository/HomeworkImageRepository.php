<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\HomeworkImage;
use App\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HomeworkImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeworkImage::class);
    }

    /** @return HomeworkImage[] */
    public function findByLessonAndFamily(Lesson $lesson, Family $family): array
    {
        return $this->findBy(['lesson' => $lesson, 'family' => $family], ['uploadedAt' => 'ASC']);
    }

    /** @return HomeworkImage[] */
    public function findByLesson(Lesson $lesson): array
    {
        return $this->findBy(['lesson' => $lesson], ['family' => 'ASC', 'uploadedAt' => 'ASC']);
    }
}
