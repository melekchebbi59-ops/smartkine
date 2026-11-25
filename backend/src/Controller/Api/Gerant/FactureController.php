<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Entity\Facture;
use App\Entity\Paiement;
use App\Entity\Seance;
use App\Repository\FactureRepository;
use App\Repository\PatientRepository;
use App\Repository\SeanceRepository;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-25 : facture établie à partir des séances planifiées / effectuées du patient.
 * US-26 : paiements partiels + calcul automatique du reste à payer.
 */
#[Route('/api/gerant/factures')]
class FactureController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, FactureRepository $repo, PatientRepository $patients): JsonResponse
    {
        $criteres = [];
        if ($pid = $request->query->getInt('patientId')) {
            $criteres['patient'] = $patients->find($pid);
        }

        $factures = $repo->findBy($criteres, ['dateEmission' => 'DESC']);
        if ($statut = $request->query->get('statut')) {
            $factures = array_values(array_filter($factures, fn (Facture $f) => $f->getStatut() === $statut));
        }

        return $this->json($this->serializer->many($factures, 'facture'));
    }

    /** Séances non encore facturées d'un patient (pour préparer la facture). */
    #[Route('/a-facturer/{patientId}', methods: ['GET'], requirements: ['patientId' => '\d+'])]
    public function aFacturer(int $patientId, PatientRepository $patients, SeanceRepository $seances): JsonResponse
    {
        $patient = $patients->find($patientId);
        if (!$patient) {
            return $this->notFound('Patient');
        }

        return $this->json($this->serializer->many($this->seancesFacturables($patient, $seances), 'seance'));
    }

    /** { patientId, seanceIds?: [] } — sans seanceIds : toutes les séances non facturées. */
    #[Route('', methods: ['POST'])]
    public function create(Request $request, PatientRepository $patients, SeanceRepository $seances, FactureRepository $repo, EntityManagerInterface $em, Notifier $notifier): JsonResponse
    {
        $data = $this->body($request);
        $patient = $patients->find((int) ($data['patientId'] ?? 0));
        if (!$patient) {
            return $this->notFound('Patient');
        }

        $facturables = $this->seancesFacturables($patient, $seances);
        if (!empty($data['seanceIds'])) {
            $ids = array_map('intval', (array) $data['seanceIds']);
            $facturables = array_filter($facturables, fn (Seance $s) => in_array($s->getId(), $ids, true));
        }
        if (!$facturables) {
            return $this->error('Aucune séance à facturer pour ce patient.');
        }

        $facture = (new Facture())->setPatient($patient)->setNumero($this->prochainNumero($repo));
        $total = 0.0;
        foreach ($facturables as $s) {
            $facture->addSeance($s);
            $total += (float) $s->getPrix();
        }
        $facture->setMontantTotal(number_format($total, 2, '.', ''))->recalculerReste();

        $em->persist($facture);
        $em->flush();

        $notifier->notifier($patient, sprintf('Nouvelle facture %s : %s DT.', $facture->getNumero(), $facture->getMontantTotal()));

        return $this->json(['message' => 'Facture ' . $facture->getNumero() . ' établie.', 'facture' => $this->serializer->facture($facture)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, FactureRepository $repo): JsonResponse
    {
        $f = $repo->find($id);
        return $f ? $this->json($this->serializer->facture($f)) : $this->notFound('Facture');
    }

    /** { montant, mode, date? } — paiement partiel. */
    #[Route('/{id}/paiements', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function payer(int $id, Request $request, FactureRepository $repo, EntityManagerInterface $em, Notifier $notifier): JsonResponse
    {
        $facture = $repo->find($id);
        if (!$facture) {
            return $this->notFound('Facture');
        }

        $data = $this->body($request);
        $montant = $this->money($data['montant'] ?? null);
        if ($montant === null || (float) $montant <= 0) {
            return $this->error('Montant invalide.');
        }
        if ((float) $montant > (float) $facture->getResteAPayer() + 0.001) {
            return $this->error(sprintf('Le montant dépasse le reste à payer (%.2f DT).', (float) $facture->getResteAPayer()));
        }

        $mode = in_array($data['mode'] ?? '', ['especes', 'carte', 'cheque', 'virement'], true) ? $data['mode'] : 'especes';
        $paiement = (new Paiement())->setMontant($montant)->setMode($mode);
        if ($date = $this->parseDate($data['date'] ?? null)) {
            $paiement->setDatePaiement($date);
        }

        $facture->addPaiement($paiement);
        $em->persist($paiement);
        $em->flush();

        $notifier->notifier($facture->getPatient(), sprintf(
            'Paiement de %s DT reçu (facture %s). Reste à payer : %s DT.',
            $montant,
            $facture->getNumero(),
            $facture->getResteAPayer()
        ));

        return $this->json(['message' => 'Paiement enregistré.', 'facture' => $this->serializer->facture($facture)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, FactureRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $facture = $repo->find($id);
        if (!$facture) {
            return $this->notFound('Facture');
        }
        if (!$facture->getPaiements()->isEmpty()) {
            return $this->error('Impossible de supprimer une facture qui a déjà des paiements.');
        }

        foreach ($facture->getSeances() as $s) {
            $s->setFacture(null);
        }
        $em->remove($facture);
        $em->flush();

        return $this->json(['message' => 'Facture supprimée, les séances sont de nouveau facturables.']);
    }

    /** Séances planifiées ou effectuées, pas annulées, pas encore facturées. */
    private function seancesFacturables($patient, SeanceRepository $seances): array
    {
        return $seances->createQueryBuilder('s')
            ->andWhere('s.patient = :p')
            ->andWhere('s.facture IS NULL')
            ->andWhere('s.statut IN (:st)')
            ->setParameter('p', $patient)
            ->setParameter('st', [Seance::STATUT_PLANIFIEE, Seance::STATUT_EFFECTUEE, Seance::STATUT_REPORT_DEMANDE])
            ->orderBy('s.dateHeure', 'ASC')
            ->getQuery()->getResult();
    }

    /** Numéro lisible : FAC-2026-0001 */
    private function prochainNumero(FactureRepository $repo): string
    {
        $annee = date('Y');
        $count = (int) $repo->createQueryBuilder('f')->select('COUNT(f.id)')
            ->andWhere('f.numero LIKE :p')->setParameter('p', "FAC-$annee-%")
            ->getQuery()->getSingleScalarResult();

        return sprintf('FAC-%s-%04d', $annee, $count + 1);
    }
}
