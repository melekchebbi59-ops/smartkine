<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

/** Messagerie interne : un utilisateur envoie, un autre reçoit (US-11, US-20, US-29). */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $contenu = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateEnvoi = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $lu = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $expediteur = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $destinataire = null;

    public function __construct()
    {
        $this->dateEnvoi = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $c): static { $this->contenu = $c; return $this; }
    public function getDateEnvoi(): ?\DateTimeImmutable { return $this->dateEnvoi; }
    public function isLu(): bool { return $this->lu; }
    public function setLu(bool $lu): static { $this->lu = $lu; return $this; }
    public function getExpediteur(): ?User { return $this->expediteur; }
    public function setExpediteur(User $u): static { $this->expediteur = $u; return $this; }
    public function getDestinataire(): ?User { return $this->destinataire; }
    public function setDestinataire(User $u): static { $this->destinataire = $u; return $this; }
}
