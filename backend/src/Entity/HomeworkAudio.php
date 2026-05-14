<?php

namespace App\Entity;

use App\Repository\HomeworkAudioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HomeworkAudioRepository::class)]
#[ORM\Table(name: 'homework_audio')]
class HomeworkAudio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 50)]
    private string $homeworkType;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 50)]
    private string $mimeType;

    #[ORM\Column]
    private int $fileSize;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getLesson(): Lesson { return $this->lesson; }
    public function setLesson(Lesson $lesson): static { $this->lesson = $lesson; return $this; }

    public function getFamily(): Family { return $this->family; }
    public function setFamily(Family $family): static { $this->family = $family; return $this; }

    public function getHomeworkType(): string { return $this->homeworkType; }
    public function setHomeworkType(string $type): static { $this->homeworkType = $type; return $this; }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getFileSize(): int { return $this->fileSize; }
    public function setFileSize(int $fileSize): static { $this->fileSize = $fileSize; return $this; }

    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
}
