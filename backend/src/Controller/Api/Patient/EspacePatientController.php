<?php

namespace App\Controller\Api\Patient;

use App\Controller\Api\ApiController;
use App\Entity\Patient;
use App\Entity\Seance;
use App\Repository\PlanReeducationRepository;
use App\Repository\SeanceRepository;
use App\Service\ApiSerializer;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace patient :
 *  US-06 planning, US-07 demande de report, US-08 demande d'annulation, US-09 plan de rééducation.
 */
#[Route('/api/patient')]
class EspacePatientController extends ApiController
{
    public function __construct(ApiSerializer $serializer, private EntityManagerInterface $em, private Notifier $notifier)
    {
        parent::__construct($serializer);
    }

    #[Route('/seances', methods: ['GET'])]
    public function seances(SeanceRepository $repo): JsonResponse
    {
        return $this->json($this->serializer->many($repo->findBy(['patient' => $this->patient()], ['dateHeure' => 'ASC']), 'seance'));
    }

    /** { dateProposee, motif } */
    #[Route('/seances/{id}/report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function report(int $id, Request $request, SeanceRepository $repo): JsonResponse
    {
        $seance = $this->maSeanceModifiable($id, $repo);
        if (is_string($seance)) {
            return $this->error($seance);
        }

        $data = $this->body($request);
        $date = $this->parseDate($data['dateProposee'] ?? null);
        if (!$date || $date < new \DateTimeImmutable()) {
            return $this->error('Proposez une nouvelle date future.');
        }

        $seance->setStatut(Seance::STATUT_REPORT_DEMANDE)
            ->setDateProposee($date)
            ->setMotifDemande($this->str($data, 'motif'));
        $this->em->flush();

        if ($seance->getKine()) {
            $this->notifier->notifier($seance->getKine(), sprintf(
                '%s demande le report de la séance du %s au %s.',
                $this->patient()->getFullName(),
                $seance->getDateHeure()->format('d/m/Y H:i'),
                $date->format('d/m/Y H:i')
            ));
        }

        return $this->json(['message' => 'Demande de report envoyée à votre kiné.', 'seance' => $this->serializer->seance($seance)]);
    }

    /** { motif } */
    #[Route('/seances/{id}/annulation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annulation(int $id, Request $request, SeanceRepository $repo): JsonResponse
    {
        $seance = $this->maSeanceModifiable($id, $repo);
        if (is_string($seance)) {
            return $this->error($seance);
        }

        $seance->setStatut(Seance::STATUT_ANNULATION_DEMANDEE)
            ->setDateProposee(null)
            ->setMotifDemande($this->str($this->body($request), 'motif'));
        $this->em->flush();

        if ($seance->getKine()) {
            $this->notifier->notifier($seance->getKine(), sprintf(
                "%s demande l'annulation de la séance du %s.",
                $this->patient()->getFullName(),
                $seance->getDateHeure()->format('d/m/Y H:i')
            ));
        }

        return $this->json(['message' => "Demande d'annulation envoyée à votre kiné.", 'seance' => $this->serializer->seance($seance)]);
    }

    #[Route('/plans', methods: ['GET'])]
    public function plans(PlanReeducationRepository $repo): JsonResponse
    {
        $plans = $repo->findBy(['patient' => $this->patient()], ['dateCreation' => 'DESC']);

        return $this->json(array_map(fn ($p) => $this->serializer->plan($p), $plans));
    }

    private function patient(): Patient
    {
        /** @var Patient $p */
        $p = $this->currentUser();
        return $p;
    }

    /** Retourne la séance, ou un message d'erreur. */
    private function maSeanceModifiable(int $id, SeanceRepository $repo): Seance|string
    {
        $seance = $repo->find($id);
        if (!$seance || $seance->getPatient() !== $this->patient()) {
            return 'Séance introuvable.';
        }
        if ($seance->getStatut() !== Seance::STATUT_PLANIFIEE) {
            return 'Seules les séances planifiées peuvent être reportées ou annulées.';
        }
        if ($seance->getDateHeure() < new \DateTimeImmutable()) {
            return 'Cette séance est déjà passée.';
        }
        return $seance;
    }
}
