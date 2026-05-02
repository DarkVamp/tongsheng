<?php

namespace App\Entity;

use App\Repository\RecordingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecordingRepository::class)]
#[ORM\Table(name: 'recordings')]
class Recording
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'recordings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 20)]
    private string $mimeType;

    #[ORM\Column]
    private int $fileSize;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deleteAt = null;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'recording', cascade: ['remove'])]
    private Collection $comments;

    public function __construct()
    {
        $this->recordedAt = new \DateTimeImmutable();
        $this->comments = new ArrayCollection();
    }

    public function getId(): int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getFileSize(): int { return $this->fileSize; }
    public function setFileSize(int $fileSize): static { $this->fileSize = $fileSize; return $this; }

    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
    public function setRecordedAt(\DateTimeImmutable $recordedAt): static { $this->recordedAt = $recordedAt; return $this; }

    public function getDeleteAt(): ?\DateTimeImmutable { return $this->deleteAt; }
    public function setDeleteAt(?\DateTimeImmutable $deleteAt): static { $this->deleteAt = $deleteAt; return $this; }

    public function getComments(): Collection { return $this->comments; }
}
