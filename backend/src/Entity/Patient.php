<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\Table(name: 'patient')]
class Patient extends User
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_REFUSE = 'refuse';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    /** Qualification / profession du patient. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $qualification = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $medecinTraitant = null;

    /** Type de traitement / pathologie (sert à choisir le kiné adapté). */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $typeTraitement = null;

    /** en_attente → valide / refuse (validation par le gérant, US-23). */
    #[ORM\Column(length: 20)]
    private string $statutInscription = self::STATUT_EN_ATTENTE;

    /** Kiné affecté par le gérant (US-24). */
    #[ORM\ManyToOne(targetEntity: Kine::class, inversedBy: 'patients')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Kine $kine = null;

    #[ORM\OneToOne(targetEntity: DossierMedical::class, mappedBy: 'patient', cascade: ['persist', 'remove'])]
    private ?DossierMedical $dossierMedical = null;

    public function getMainRole(): string
    {
        return 'ROLE_PATIENT';
    }

    public function getType(): string
    {
        return 'patient';
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getQualification(): ?string
    {
        return $this->qualification;
    }

    public function setQualification(?string $qualification): static
    {
        $this->qualification = $qualification;
        return $this;
    }

    public function getMedecinTraitant(): ?string
    {
        return $this->medecinTraitant;
    }

    public function setMedecinTraitant(?string $medecinTraitant): static
    {
        $this->medecinTraitant = $medecinTraitant;
        return $this;
    }

    public function getTypeTraitement(): ?string
    {
        return $this->typeTraitement;
    }

    public function setTypeTraitement(?string $typeTraitement): static
    {
        $this->typeTraitement = $typeTraitement;
        return $this;
    }

    public function getStatutInscription(): string
    {
        return $this->statutInscription;
    }

    public function setStatutInscription(string $statutInscription): static
    {
        $this->statutInscription = $statutInscription;
        return $this;
    }

    public function isValide(): bool
    {
        return $this->statutInscription === self::STATUT_VALIDE;
    }

    public function getKine(): ?Kine
    {
        return $this->kine;
    }

    public function setKine(?Kine $kine): static
    {
        $this->kine = $kine;
        return $this;
    }

    public function getDossierMedical(): ?DossierMedical
    {
        return $this->dossierMedical;
    }

    public function setDossierMedical(DossierMedical $dossierMedical): static
    {
        if ($dossierMedical->getPatient() !== $this) {
            $dossierMedical->setPatient($this);
        }
        $this->dossierMedical = $dossierMedical;
        return $this;
    }
}
