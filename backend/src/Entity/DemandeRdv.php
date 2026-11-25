<?php

namespace App\Entity;

use App\Repository\DemandeRdvRepository;
use Doctrine\ORM\Mapping as ORM;

/** Demande de premier rendez-vous envoyée par un visiteur (US-02). */
#[ORM\Entity(repositoryClass: DemandeRdvRepository::class)]
#[ORM\Table(name: 'demande_rdv')]
class DemandeRdv
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTEE = 'acceptee';
    public const STATUT_REFUSEE = 'refusee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateSouhaitee = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    /** Date/heure du RDV fixée par le gérant lors de l'acceptation. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateRdvConfirmee = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Gérant qui a traité la demande (relation « traite »). */
    #[ORM\ManyToOne(targetEntity: Gerant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Gerant $gerant = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = strtolower(trim($email)); return $this; }
    public function getDateSouhaitee(): ?\DateTimeImmutable { return $this->dateSouhaitee; }
    public function setDateSouhaitee(\DateTimeImmutable $d): static { $this->dateSouhaitee = $d; return $this; }
    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $motif): static { $this->motif = $motif; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateRdvConfirmee(): ?\DateTimeImmutable { return $this->dateRdvConfirmee; }
    public function setDateRdvConfirmee(?\DateTimeImmutable $d): static { $this->dateRdvConfirmee = $d; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getGerant(): ?Gerant { return $this->gerant; }
    public function setGerant(?Gerant $gerant): static { $this->gerant = $gerant; return $this; }
}
