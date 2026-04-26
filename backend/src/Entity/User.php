<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $familyName;

    #[ORM\Column(length: 20)]
    private string $role = 'family'; // 'family' | 'teacher'

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $apiToken = null;

    #[ORM\Column(length: 5, options: ['default' => 'zh'])]
    private string $locale = 'zh';

    #[ORM\OneToMany(targetEntity: Recording::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $recordings;

    public function __construct()
    {
        $this->recordings = new ArrayCollection();
    }

    public function getId(): int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getFamilyName(): string { return $this->familyName; }
    public function setFamilyName(string $familyName): static { $this->familyName = $familyName; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function getApiToken(): ?string { return $this->apiToken; }
    public function setApiToken(?string $apiToken): static { $this->apiToken = $apiToken; return $this; }

    public function getRecordings(): Collection { return $this->recordings; }

    // UserInterface
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return ['ROLE_' . strtoupper($this->role)]; }
    public function eraseCredentials(): void {}

    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): static { $this->locale = $locale; return $this; }

    public function isTeacher(): bool { return $this->role === 'teacher'; }
}
