<?php

namespace App\Entity;

use App\Repository\SeanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Séance planifiée par le kiné (US-15). */
#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'seance')]
class Seance
{
    public const STATUT_PLANIFIEE = 'planifiee';
    public const STATUT_EFFECTUEE = 'effectuee';
    public const STATUT_ANNULEE = 'annulee';
    public const STATUT_REPORT_DEMANDE = 'report_demande';
    public const STATUT_ANNULATION_DEMANDEE = 'annulation_demandee';

    /** Tarif utilisé si la séance ne contient aucun exercice tarifé (en DT). */
    public const TARIF_PAR_DEFAUT = '40.00';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Date + heure de la séance. */
    #[ORM\Column]
    private ?\DateTimeImmutable $dateHeure = null;

    /** Durée en minutes. */
    #[ORM\Column(options: ['default' => 45])]
    private int $duree = 45;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objectif = null;

    #[ORM\Column(length: 30)]
    private string $statut = self::STATUT_PLANIFIEE;

    /** Nouvelle date proposée par le patient lors d'une demande de report (US-07). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateProposee = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifDemande = null;

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: Kine::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Kine $kine = null;

    #[ORM\ManyToOne(targetEntity: PlanReeducation::class, inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PlanReeducation $plan = null;

    /** @var Collection<int, Exercice> Relation « inclut » Séance * — * Exercice. */
    #[ORM\ManyToMany(targetEntity: Exercice::class)]
    #[ORM\JoinTable(name: 'seance_exercice')]
    private Collection $exercices;

    /** Facture dans laquelle la séance a été facturée (évite la double facturation). */
    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Facture $facture = null;

    public function __construct()
    {
        $this->exercices = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getDateHeure(): ?\DateTimeImmutable { return $this->dateHeure; }
    public function setDateHeure(\DateTimeImmutable $d): static { $this->dateHeure = $d; return $this; }
    public function getDuree(): int { return $this->duree; }
    public function setDuree(int $duree): static { $this->duree = $duree; return $this; }
    public function getObjectif(): ?string { return $this->objectif; }
    public function setObjectif(?string $o): static { $this->objectif = $o; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateProposee(): ?\DateTimeImmutable { return $this->dateProposee; }
    public function setDateProposee(?\DateTimeImmutable $d): static { $this->dateProposee = $d; return $this; }
    public function getMotifDemande(): ?string { return $this->motifDemande; }
    public function setMotifDemande(?string $m): static { $this->motifDemande = $m; return $this; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(Patient $patient): static { $this->patient = $patient; return $this; }
    public function getKine(): ?Kine { return $this->kine; }
    public function setKine(?Kine $kine): static { $this->kine = $kine; return $this; }
    public function getPlan(): ?PlanReeducation { return $this->plan; }
    public function setPlan(?PlanReeducation $plan): static { $this->plan = $plan; return $this; }
    public function getFacture(): ?Facture { return $this->facture; }
    public function setFacture(?Facture $facture): static { $this->facture = $facture; return $this; }

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

    /** Prix de la séance = somme des coûts des exercices, sinon tarif par défaut. */
    public function getPrix(): string
    {
        $total = 0.0;
        foreach ($this->exercices as $e) {
            $total += (float) $e->getCout();
        }
        return $total > 0 ? number_format($total, 2, '.', '') : self::TARIF_PAR_DEFAUT;
    }
}
