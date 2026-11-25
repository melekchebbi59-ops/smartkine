<?php

namespace App\Entity;

use App\Repository\KineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KineRepository::class)]
#[ORM\Table(name: 'kine')]
class Kine extends User
{
    /** Spécialité (ex. : rééducation du sport, neurologie...) pour aider l'affectation. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $specialite = null;

    /** @var Collection<int, Patient> Patients soignés (relation « soigne »). */
    #[ORM\OneToMany(targetEntity: Patient::class, mappedBy: 'kine')]
    private Collection $patients;

    public function __construct()
    {
        parent::__construct();
        $this->patients = new ArrayCollection();
    }

    public function getMainRole(): string
    {
        return 'ROLE_KINE';
    }

    public function getType(): string
    {
        return 'kine';
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(?string $specialite): static
    {
        $this->specialite = $specialite;
        return $this;
    }

    /** @return Collection<int, Patient> */
    public function getPatients(): Collection
    {
        return $this->patients;
    }
}
