<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Paiement partiel d'une facture (US-26). */
#[ORM\Entity]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $montant = '0.00';

    #[ORM\Column]
    private ?\DateTimeImmutable $datePaiement = null;

    /** espèces, carte, chèque, virement */
    #[ORM\Column(length: 20)]
    private string $mode = 'especes';

    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Facture $facture = null;

    public function __construct()
    {
        $this->datePaiement = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMontant(): string { return $this->montant; }
    public function setMontant(string $m): static { $this->montant = $m; return $this; }
    public function getDatePaiement(): ?\DateTimeImmutable { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeImmutable $d): static { $this->datePaiement = $d; return $this; }
    public function getMode(): string { return $this->mode; }
    public function setMode(string $mode): static { $this->mode = $mode; return $this; }
    public function getFacture(): ?Facture { return $this->facture; }
    public function setFacture(Facture $facture): static { $this->facture = $facture; return $this; }
}
