<?php

namespace App\Repository;

use App\Entity\HomeworkAudio;
use App\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HomeworkAudioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeworkAudio::class);
    }

    /** @return HomeworkAudio[] */
    public function findByLesson(Lesson $lesson): array
    {
        return $this->findBy(['lesson' => $lesson], ['uploadedAt' => 'ASC']);
    }

    /** @return HomeworkAudio[] */
    public function findByLessonAndFamily(\App\Entity\Lesson $lesson, \App\Entity\Family $family): array
    {
        return $this->findBy(['lesson' => $lesson, 'family' => $family], ['uploadedAt' => 'ASC']);
    }
}
