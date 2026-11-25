<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Entity\DemandeRdv;
use App\Entity\Paiement;
use App\Entity\Patient;
use App\Entity\Seance;
use App\Repository\FactureRepository;
use App\Repository\KineRepository;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-27 : activité globale (séances du jour et de la semaine).
 * US-28 : statistiques clés (patients par kiné, recettes journalières et mensuelles).
 */
#[Route('/api/gerant')]
class StatistiquesController extends ApiController
{
    #[Route('/activite', methods: ['GET'])]
    public function activite(SeanceRepository $seances): JsonResponse
    {
        $aujourdhui = new \DateTimeImmutable('today');
        $lundi = new \DateTimeImmutable('monday this week');

        return $this->json([
            'jour' => $this->serializer->many($seances->entre($aujourdhui, $aujourdhui->modify('+1 day')), 'seance'),
            'semaine' => $this->serializer->many($seances->entre($lundi, $lundi->modify('+7 days')), 'seance'),
        ]);
    }

    #[Route('/statistiques', methods: ['GET'])]
    public function statistiques(EntityManagerInterface $em, KineRepository $kines, SeanceRepository $seances, FactureRepository $factures): JsonResponse
    {
        $aujourdhui = new \DateTimeImmutable('today');
        $debutMois = new \DateTimeImmutable('first day of this month 00:00');
        $lundi = new \DateTimeImmutable('monday this week');

        // Patients par kiné
        $patientsParKine = [];
        foreach ($kines->findBy([], ['lastName' => 'ASC']) as $k) {
            $patientsParKine[] = ['kine' => $k->getFullName(), 'patients' => $k->getPatients()->count(), 'actif' => $k->isActive()];
        }

        // Recettes : somme des paiements
        $paiements = $em->getRepository(Paiement::class)->createQueryBuilder('p')
            ->andWhere('p.datePaiement >= :d')->setParameter('d', (new \DateTimeImmutable('first day of this month'))->modify('-11 months')->setTime(0, 0))
            ->getQuery()->getResult();

        $recetteJour = 0.0;
        $recetteMois = 0.0;
        $parJour = [];   // 30 derniers jours
        $parMois = [];   // 12 derniers mois
        for ($i = 29; $i >= 0; $i--) {
            $parJour[$aujourdhui->modify("-$i days")->format('Y-m-d')] = 0.0;
        }
        for ($i = 11; $i >= 0; $i--) {
            $parMois[$debutMois->modify("-$i months")->format('Y-m')] = 0.0;
        }

        foreach ($paiements as $p) {
            $m = (float) $p->getMontant();
            $d = $p->getDatePaiement();
            if ($d >= $aujourdhui) {
                $recetteJour += $m;
            }
            if ($d >= $debutMois) {
                $recetteMois += $m;
            }
            if (isset($parJour[$d->format('Y-m-d')])) {
                $parJour[$d->format('Y-m-d')] += $m;
            }
            if (isset($parMois[$d->format('Y-m')])) {
                $parMois[$d->format('Y-m')] += $m;
            }
        }

        // Reste à encaisser
        $resteTotal = 0.0;
        foreach ($factures->findAll() as $f) {
            $resteTotal += (float) $f->getResteAPayer();
        }

        $count = fn (string $class, array $crit = []) => $em->getRepository($class)->count($crit);

        return $this->json([
            'seancesJour' => count(array_filter($seances->entre($aujourdhui, $aujourdhui->modify('+1 day')), fn (Seance $s) => $s->getStatut() !== Seance::STATUT_ANNULEE)),
            'seancesSemaine' => count(array_filter($seances->entre($lundi, $lundi->modify('+7 days')), fn (Seance $s) => $s->getStatut() !== Seance::STATUT_ANNULEE)),
            'patientsActifs' => $count(Patient::class, ['statutInscription' => Patient::STATUT_VALIDE, 'active' => true]),
            'demandesEnAttente' => $count(DemandeRdv::class, ['statut' => DemandeRdv::STATUT_EN_ATTENTE]),
            'inscriptionsEnAttente' => $count(Patient::class, ['statutInscription' => Patient::STATUT_EN_ATTENTE]),
            'patientsSansKine' => $count(Patient::class, ['statutInscription' => Patient::STATUT_VALIDE, 'kine' => null]),
            'recetteJour' => number_format($recetteJour, 2, '.', ''),
            'recetteMois' => number_format($recetteMois, 2, '.', ''),
            'resteAEncaisser' => number_format($resteTotal, 2, '.', ''),
            'patientsParKine' => $patientsParKine,
            'recettesJournalieres' => array_map(fn ($k, $v) => ['date' => $k, 'montant' => round($v, 2)], array_keys($parJour), $parJour),
            'recettesMensuelles' => array_map(fn ($k, $v) => ['mois' => $k, 'montant' => round($v, 2)], array_keys($parMois), $parMois),
        ]);
    }
}
