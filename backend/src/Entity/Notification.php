<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/** Notification affichée dans l'espace de l'utilisateur (US-12). */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $destinataire = null;

    #[ORM\Column(length: 255)]
    private ?string $texte = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $lue = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getDestinataire(): ?User { return $this->destinataire; }
    public function setDestinataire(User $u): static { $this->destinataire = $u; return $this; }
    public function getTexte(): ?string { return $this->texte; }
    public function setTexte(string $t): static { $this->texte = $t; return $this; }
    public function isLue(): bool { return $this->lue; }
    public function setLue(bool $lue): static { $this->lue = $lue; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}
