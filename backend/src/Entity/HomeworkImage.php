<?php

namespace App\Entity;

use App\Repository\HomeworkImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HomeworkImageRepository::class)]
#[ORM\Table(name: 'homework_images')]
class HomeworkImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 255)]
    private string $filePath;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 50)]
    private string $mimeType;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getLesson(): Lesson { return $this->lesson; }
    public function setLesson(Lesson $lesson): static { $this->lesson = $lesson; return $this; }

    public function getFamily(): Family { return $this->family; }
    public function setFamily(Family $family): static { $this->family = $family; return $this; }

    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $name): static { $this->originalFilename = $name; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
}
