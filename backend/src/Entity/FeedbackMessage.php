<?php

namespace App\Entity;

use App\Repository\FeedbackMessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeedbackMessageRepository::class)]
#[ORM\Table(name: 'feedback_messages')]
class FeedbackMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: FeedbackAttachment::class, mappedBy: 'message', cascade: ['remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): int { return $this->id; }

    public function getLesson(): Lesson { return $this->lesson; }
    public function setLesson(Lesson $lesson): static { $this->lesson = $lesson; return $this; }

    public function getStudent(): User { return $this->student; }
    public function setStudent(User $student): static { $this->student = $student; return $this; }

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }

    public function getText(): ?string { return $this->text; }
    public function setText(?string $text): static { $this->text = $text; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getAttachments(): Collection { return $this->attachments; }
}
