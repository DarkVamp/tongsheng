<?php

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'invitations')]
class Invitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 20)]
    private string $role = 'family'; // 'family' | 'family_member'

    // Nur gesetzt wenn role='family_member' — zeigt auf die family_group_id der zugehörigen Familie
    #[ORM\Column(nullable: true)]
    private ?int $familyGroupId = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $invitedBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+7 days');
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getFamilyGroupId(): ?int { return $this->familyGroupId; }
    public function setFamilyGroupId(?int $familyGroupId): static { $this->familyGroupId = $familyGroupId; return $this; }

    public function getToken(): string { return $this->token; }

    public function getInvitedBy(): User { return $this->invitedBy; }
    public function setInvitedBy(User $invitedBy): static { $this->invitedBy = $invitedBy; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }

    public function isExpired(): bool { return $this->expiresAt < new \DateTimeImmutable(); }
}
