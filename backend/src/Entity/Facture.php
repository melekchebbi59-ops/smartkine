<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Facture d'un patient, réglée par paiements partiels (US-25, US-26). */
#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[ORM\Table(name: 'facture')]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $numero = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateEmission = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $montantTotal = '0.00';

    /** Calculé automatiquement : total − somme des paiements. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $resteAPayer = '0.00';

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    /** @var Collection<int, Seance> */
    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'facture')]
    private Collection $seances;

    /** @var Collection<int, Paiement> */
    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'facture', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['datePaiement' => 'ASC'])]
    private Collection $paiements;

    public function __construct()
    {
        $this->dateEmission = new \DateTimeImmutable();
        $this->seances = new ArrayCollection();
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }
    public function getDateEmission(): ?\DateTimeImmutable { return $this->dateEmission; }
    public function getMontantTotal(): string { return $this->montantTotal; }
    public function setMontantTotal(string $m): static { $this->montantTotal = $m; return $this; }
    public function getResteAPayer(): string { return $this->resteAPayer; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(Patient $patient): static { $this->patient = $patient; return $this; }

    /** @return Collection<int, Seance> */
    public function getSeances(): Collection { return $this->seances; }

    public function addSeance(Seance $seance): static
    {
        if (!$this->seances->contains($seance)) {
            $this->seances->add($seance);
            $seance->setFacture($this);
        }
        return $this;
    }

    /** @return Collection<int, Paiement> */
    public function getPaiements(): Collection { return $this->paiements; }

    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setFacture($this);
        }
        $this->recalculerReste();
        return $this;
    }

    public function getMontantPaye(): string
    {
        $paye = 0.0;
        foreach ($this->paiements as $p) {
            $paye += (float) $p->getMontant();
        }
        return number_format($paye, 2, '.', '');
    }

    /** Reste à payer = total − payé (jamais négatif). */
    public function recalculerReste(): static
    {
        $reste = max(0, (float) $this->montantTotal - (float) $this->getMontantPaye());
        $this->resteAPayer = number_format($reste, 2, '.', '');
        return $this;
    }

    public function getStatut(): string
    {
        if ((float) $this->resteAPayer <= 0) {
            return 'payee';
        }
        return $this->paiements->isEmpty() ? 'impayee' : 'partielle';
    }
}
