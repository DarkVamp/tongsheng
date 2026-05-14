<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
#[ORM\Table(name: 'lessons')]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: false, onDelete: 'CASCADE')]
    private User $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $homeworkAssigned = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $homeworkTypes = null;

    #[ORM\OneToMany(targetEntity: Attendance::class, mappedBy: 'lesson', cascade: ['remove'])]
    private Collection $attendances;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): int { return $this->id; }

    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }

    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isHomeworkAssigned(): bool { return $this->homeworkAssigned; }
    public function setHomeworkAssigned(bool $v): static { $this->homeworkAssigned = $v; return $this; }

    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $summary): static { $this->summary = $summary; return $this; }

    public function getHomeworkTypes(): ?array { return $this->homeworkTypes; }
    public function setHomeworkTypes(?array $types): static { $this->homeworkTypes = $types; return $this; }

    public function getAttendances(): Collection { return $this->attendances; }
}
