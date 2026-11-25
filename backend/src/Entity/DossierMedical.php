<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Dossier médical unique du patient (US-17). Accessible au kiné traitant et au gérant. */
#[ORM\Entity]
#[ORM\Table(name: 'dossier_medical')]
class DossierMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Patient::class, inversedBy: 'dossierMedical')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionEtat = null;

    /** Tension artérielle, ex. « 12/8 ». */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tensionArterielle = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?string $poids = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $glycemie = null;

    /** Antécédents et autres maladies. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $autresMaladies = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(Patient $patient): static { $this->patient = $patient; return $this; }
    public function getDescriptionEtat(): ?string { return $this->descriptionEtat; }
    public function setDescriptionEtat(?string $v): static { $this->descriptionEtat = $v; return $this; }
    public function getTensionArterielle(): ?string { return $this->tensionArterielle; }
    public function setTensionArterielle(?string $v): static { $this->tensionArterielle = $v; return $this; }
    public function getPoids(): ?string { return $this->poids; }
    public function setPoids(?string $v): static { $this->poids = $v; return $this; }
    public function getGlycemie(): ?string { return $this->glycemie; }
    public function setGlycemie(?string $v): static { $this->glycemie = $v; return $this; }
    public function getAutresMaladies(): ?string { return $this->autresMaladies; }
    public function setAutresMaladies(?string $v): static { $this->autresMaladies = $v; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
