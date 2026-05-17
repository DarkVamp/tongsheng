<?php

namespace App\Entity;

use App\Repository\FeedbackAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeedbackAttachmentRepository::class)]
#[ORM\Table(name: 'feedback_attachments')]
class FeedbackAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: FeedbackMessage::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FeedbackMessage $message;

    #[ORM\Column(length: 10)]
    private string $type; // 'audio' | 'image'

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 50)]
    private string $mimeType;

    #[ORM\Column]
    private int $fileSize;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getMessage(): FeedbackMessage { return $this->message; }
    public function setMessage(FeedbackMessage $message): static { $this->message = $message; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getFileSize(): int { return $this->fileSize; }
    public function setFileSize(int $fileSize): static { $this->fileSize = $fileSize; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
