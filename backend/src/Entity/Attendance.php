<?php

namespace App\Entity;

use App\Repository\AttendanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttendanceRepository::class)]
#[ORM\Table(name: 'attendance')]
#[ORM\UniqueConstraint(name: 'UNIQ_LESSON_STUDENT', columns: ['lesson_id', 'student_id'])]
class Attendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Lesson::class, inversedBy: 'attendances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[ORM\Column]
    private bool $present = false;

    public function getId(): int { return $this->id; }

    public function getLesson(): Lesson { return $this->lesson; }
    public function setLesson(Lesson $lesson): static { $this->lesson = $lesson; return $this; }

    public function getStudent(): User { return $this->student; }
    public function setStudent(User $student): static { $this->student = $student; return $this; }

    public function isPresent(): bool { return $this->present; }
    public function setPresent(bool $present): static { $this->present = $present; return $this; }
}
