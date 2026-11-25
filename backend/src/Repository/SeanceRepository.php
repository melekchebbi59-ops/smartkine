<?php

namespace App\Repository;

use App\Entity\Kine;
use App\Entity\Seance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Seance>
 */
class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    /** Séances entre deux dates (pour un kiné ou tout le centre). */
    public function entre(\DateTimeImmutable $debut, \DateTimeImmutable $fin, ?Kine $kine = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.dateHeure >= :debut AND s.dateHeure < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('s.dateHeure', 'ASC');

        if ($kine) {
            $qb->andWhere('s.kine = :kine')->setParameter('kine', $kine);
        }

        return $qb->getQuery()->getResult();
    }

    /** Vrai si le kiné a déjà une séance active qui chevauche ce créneau. */
    public function conflit(Kine $kine, \DateTimeImmutable $debut, int $duree, ?int $saufId = null): bool
    {
        $fin = $debut->modify("+$duree minutes");
        $candidates = $this->createQueryBuilder('s')
            ->andWhere('s.kine = :kine')
            ->andWhere('s.statut NOT IN (:inactifs)')
            ->andWhere('s.dateHeure < :fin AND s.dateHeure > :debutMoins')
            ->setParameter('kine', $kine)
            ->setParameter('inactifs', [Seance::STATUT_ANNULEE])
            ->setParameter('fin', $fin)
            ->setParameter('debutMoins', $debut->modify('-4 hours'))
            ->getQuery()->getResult();

        foreach ($candidates as $s) {
            if ($saufId && $s->getId() === $saufId) {
                continue;
            }
            $finS = $s->getDateHeure()->modify('+' . $s->getDuree() . ' minutes');
            if ($s->getDateHeure() < $fin && $finS > $debut) {
                return true;
            }
        }

        return false;
    }

    /** Demandes de report / annulation en attente pour un kiné. */
    public function demandesEnAttente(Kine $kine): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.kine = :kine')
            ->andWhere('s.statut IN (:st)')
            ->setParameter('kine', $kine)
            ->setParameter('st', [Seance::STATUT_REPORT_DEMANDE, Seance::STATUT_ANNULATION_DEMANDEE])
            ->orderBy('s.dateHeure', 'ASC')
            ->getQuery()->getResult();
    }
}
