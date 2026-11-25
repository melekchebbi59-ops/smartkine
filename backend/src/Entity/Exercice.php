<?php

namespace App\Entity;

use App\Repository\ExerciceRepository;
use Doctrine\ORM\Mapping as ORM;

/** Banque d'exercices (US-19, US-30). */
#[ORM\Entity(repositoryClass: ExerciceRepository::class)]
#[ORM\Table(name: 'exercice')]
class Exercice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    /** Durée en minutes. */
    #[ORM\Column]
    private int $duree = 10;

    /** Coût (tarif) en DT, fixé par le gérant. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $cout = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objectif = null;

    /** Chemin public de l'image, ex. /uploads/exercices/abc.jpg */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getDuree(): int { return $this->duree; }
    public function setDuree(int $duree): static { $this->duree = $duree; return $this; }
    public function getCout(): string { return $this->cout; }
    public function setCout(string $cout): static { $this->cout = $cout; return $this; }
    public function getObjectif(): ?string { return $this->objectif; }
    public function setObjectif(?string $objectif): static { $this->objectif = $objectif; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
}
