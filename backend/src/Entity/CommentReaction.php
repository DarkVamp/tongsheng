<?php

namespace App\Entity;

use App\Repository\CommentReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentReactionRepository::class)]
#[ORM\Table(name: 'comment_reactions')]
#[ORM\UniqueConstraint(name: 'uniq_comment_user', columns: ['comment_id', 'user_id'])]
class CommentReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'reactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Comment $comment;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    // 'thumbs_up' | 'heart' | 'thumbs_down'
    #[ORM\Column(length: 20)]
    private string $type;

    public function getId(): int { return $this->id; }

    public function getComment(): Comment { return $this->comment; }
    public function setComment(Comment $comment): static { $this->comment = $comment; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
}
