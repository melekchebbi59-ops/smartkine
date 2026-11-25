<?php

namespace App\Entity;

use App\Repository\PlanReeducationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Plan de rééducation établi par le kiné pour un patient (US-18). */
#[ORM\Entity(repositoryClass: PlanReeducationRepository::class)]
#[ORM\Table(name: 'plan_reeducation')]
class PlanReeducation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objectifs = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Kine::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Kine $kine = null;

    /** @var Collection<int, Exercice> Exercices prescrits, choisis dans la banque. */
    #[ORM\ManyToMany(targetEntity: Exercice::class)]
    #[ORM\JoinTable(name: 'plan_exercice')]
    private Collection $exercices;

    /** @var Collection<int, Seance> */
    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'plan')]
    #[ORM\OrderBy(['dateHeure' => 'ASC'])]
    private Collection $seances;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->exercices = new ArrayCollection();
        $this->seances = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getObjectifs(): ?string { return $this->objectifs; }
    public function setObjectifs(?string $o): static { $this->objectifs = $o; return $this; }
    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(Patient $patient): static { $this->patient = $patient; return $this; }
    public function getKine(): ?Kine { return $this->kine; }
    public function setKine(?Kine $kine): static { $this->kine = $kine; return $this; }

    /** @return Collection<int, Exercice> */
    public function getExercices(): Collection { return $this->exercices; }

    /** @param Exercice[] $exercices */
    public function replaceExercices(array $exercices): static
    {
        $this->exercices->clear();
        foreach ($exercices as $e) {
            $this->exercices->add($e);
        }
        return $this;
    }

    /** @return Collection<int, Seance> */
    public function getSeances(): Collection { return $this->seances; }
}
