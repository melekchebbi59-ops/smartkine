<?php

namespace App\Controller\Api\Kine;

use App\Controller\Api\ApiController;
use App\Entity\Kine;
use App\Entity\Seance;
use App\Repository\ExerciceRepository;
use App\Repository\PatientRepository;
use App\Repository\PlanReeducationRepository;
use App\Repository\SeanceRepository;
use App\Service\ApiSerializer;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-15 : planifier / modifier / annuler les séances (avec objectifs et exercices).
 * US-16 : valider ou refuser les demandes de report (et d'annulation) des patients.
 */
#[Route('/api/kine/seances')]
class SeanceController extends ApiController
{
    public function __construct(
        ApiSerializer $serializer,
        private EntityManagerInterface $em,
        private SeanceRepository $seances,
        private Notifier $notifier,
    ) {
        parent::__construct($serializer);
    }

    /** Agenda du kiné : ?debut=2026-11-23&fin=2026-11-30 (par défaut : semaine en cours). */
    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $debut = $this->parseDate($request->query->get('debut')) ?? new \DateTimeImmutable('monday this week');
        $fin = $this->parseDate($request->query->get('fin')) ?? $debut->modify('+7 days');

        return $this->json($this->serializer->many($this->seances->entre($debut->setTime(0, 0), $fin->setTime(0, 0), $this->kine()), 'seance'));
    }

    #[Route('/demandes', methods: ['GET'])]
    public function demandes(): JsonResponse
    {
        return $this->json($this->serializer->many($this->seances->demandesEnAttente($this->kine()), 'seance'));
    }

    /**
     * { patientId, dateHeure, duree, objectif, exerciceIds[], planId, nombre, intervalleJours }
     * « nombre » permet de planifier une série (ex. : 10 séances, 1 par semaine).
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request, PatientRepository $patients, ExerciceRepository $exercices, PlanReeducationRepository $plans): JsonResponse
    {
        $data = $this->body($request);
        $kine = $this->kine();

        $patient = $patients->find((int) ($data['patientId'] ?? 0));
        if (!$patient || $patient->getKine() !== $kine) {
            return $this->error('Patient introuvable ou non affecté à vous.');
        }

        $date = $this->parseDate($data['dateHeure'] ?? null);
        $duree = max(15, (int) ($data['duree'] ?? 45));
        $nombre = min(20, max(1, (int) ($data['nombre'] ?? 1)));
        $intervalle = min(30, max(1, (int) ($data['intervalleJours'] ?? 7)));

        if (!$date) {
            return $this->error('La date et l\'heure sont obligatoires.');
        }
        if ($date < new \DateTimeImmutable()) {
            return $this->error('Impossible de planifier une séance dans le passé.');
        }

        $plan = null;
        if (!empty($data['planId'])) {
            $plan = $plans->find((int) $data['planId']);
            if (!$plan || $plan->getPatient() !== $patient) {
                return $this->error('Plan de rééducation invalide.');
            }
        }

        $listeExercices = $exercices->findBy(['id' => array_map('intval', (array) ($data['exerciceIds'] ?? []))]);

        $creees = [];
        for ($i = 0; $i < $nombre; $i++) {
            $d = $date->modify('+' . ($i * $intervalle) . ' days');
            if ($this->seances->conflit($kine, $d, $duree)) {
                return $this->error(sprintf('Vous avez déjà une séance le %s. Aucune séance n\'a été créée.', $d->format('d/m/Y à H:i')), Response::HTTP_CONFLICT);
            }

            $s = (new Seance())
                ->setPatient($patient)
                ->setKine($kine)
                ->setDateHeure($d)
                ->setDuree($duree)
                ->setObjectif($this->str($data, 'objectif'))
                ->setPlan($plan)
                ->replaceExercices($listeExercices);
            $this->em->persist($s);
            $creees[] = $s;
        }

        $this->em->flush();
        $this->notifier->notifier($patient, $nombre > 1
            ? sprintf('%d nouvelles séances planifiées à partir du %s.', $nombre, $date->format('d/m/Y à H:i'))
            : sprintf('Nouvelle séance planifiée le %s.', $date->format('d/m/Y à H:i')));

        return $this->json([
            'message' => $nombre > 1 ? "$nombre séances planifiées." : 'Séance planifiée.',
            'seances' => $this->serializer->many($creees, 'seance'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, ExerciceRepository $exercices): JsonResponse
    {
        $seance = $this->maSeance($id);
        if (!$seance) {
            return $this->notFound('Séance');
        }

        $data = $this->body($request);
        $ancienneDate = $seance->getDateHeure();

        if (isset($data['dateHeure'])) {
            $date = $this->parseDate($data['dateHeure']);
            if (!$date) {
                return $this->error('Date invalide.');
            }
            $duree = isset($data['duree']) ? max(15, (int) $data['duree']) : $seance->getDuree();
            if ($this->seances->conflit($this->kine(), $date, $duree, $seance->getId())) {
                return $this->error('Ce créneau est déjà occupé.', Response::HTTP_CONFLICT);
            }
            $seance->setDateHeure($date);
        }
        if (isset($data['duree'])) {
            $seance->setDuree(max(15, (int) $data['duree']));
        }
        if (array_key_exists('objectif', $data)) {
            $seance->setObjectif($this->str($data, 'objectif'));
        }
        if (array_key_exists('exerciceIds', $data)) {
            if ($seance->getFacture()) {
                return $this->error('Séance déjà facturée : les exercices ne peuvent plus être modifiés.');
            }
            $seance->replaceExercices($exercices->findBy(['id' => array_map('intval', (array) $data['exerciceIds'])]));
        }
        if (isset($data['statut']) && in_array($data['statut'], [Seance::STATUT_PLANIFIEE, Seance::STATUT_EFFECTUEE], true)) {
            $seance->setStatut($data['statut']);
        }

        $this->em->flush();

        if ($ancienneDate != $seance->getDateHeure()) {
            $this->notifier->notifier($seance->getPatient(), sprintf(
                'Votre séance du %s a été déplacée au %s.',
                $ancienneDate->format('d/m/Y à H:i'),
                $seance->getDateHeure()->format('d/m/Y à H:i')
            ));
        }

        return $this->json(['message' => 'Séance modifiée.', 'seance' => $this->serializer->seance($seance)]);
    }

    #[Route('/{id}/annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annuler(int $id): JsonResponse
    {
        $seance = $this->maSeance($id);
        if (!$seance) {
            return $this->notFound('Séance');
        }

        $seance->setStatut(Seance::STATUT_ANNULEE)->setDateProposee(null);
        $this->em->flush();
        $this->notifier->notifier($seance->getPatient(), sprintf('Votre séance du %s a été annulée par votre kiné.', $seance->getDateHeure()->format('d/m/Y à H:i')));

        return $this->json(['message' => 'Séance annulée.', 'seance' => $this->serializer->seance($seance)]);
    }

    /** Traiter une demande du patient : /report/valider, /report/refuser, /annulation/valider, /annulation/refuser */
    #[Route('/{id}/{type}/{decision}', methods: ['POST'], requirements: ['id' => '\d+', 'type' => 'report|annulation', 'decision' => 'valider|refuser'])]
    public function traiterDemande(int $id, string $type, string $decision): JsonResponse
    {
        $seance = $this->maSeance($id);
        if (!$seance) {
            return $this->notFound('Séance');
        }

        $attendu = $type === 'report' ? Seance::STATUT_REPORT_DEMANDE : Seance::STATUT_ANNULATION_DEMANDEE;
        if ($seance->getStatut() !== $attendu) {
            return $this->error('Aucune demande en attente sur cette séance.');
        }

        $ancienne = $seance->getDateHeure()->format('d/m/Y à H:i');

        if ($decision === 'refuser') {
            $seance->setStatut(Seance::STATUT_PLANIFIEE)->setDateProposee(null);
            $texte = sprintf('Votre demande %s pour la séance du %s a été refusée. La séance est maintenue.', $type === 'report' ? 'de report' : "d'annulation", $ancienne);
        } elseif ($type === 'report') {
            $nouvelle = $seance->getDateProposee();
            if ($this->seances->conflit($this->kine(), $nouvelle, $seance->getDuree(), $seance->getId())) {
                return $this->error('Vous avez déjà une séance sur le créneau proposé. Refusez ou déplacez la séance manuellement.', Response::HTTP_CONFLICT);
            }
            $seance->setDateHeure($nouvelle)->setStatut(Seance::STATUT_PLANIFIEE)->setDateProposee(null);
            $texte = sprintf('Report accepté : votre séance du %s est déplacée au %s.', $ancienne, $nouvelle->format('d/m/Y à H:i'));
        } else {
            $seance->setStatut(Seance::STATUT_ANNULEE);
            $texte = sprintf('Annulation acceptée pour la séance du %s.', $ancienne);
        }

        $this->em->flush();
        $this->notifier->notifier($seance->getPatient(), $texte);

        return $this->json(['message' => 'Demande traitée.', 'seance' => $this->serializer->seance($seance)]);
    }

    private function kine(): Kine
    {
        /** @var Kine $k */
        $k = $this->currentUser();
        return $k;
    }

    private function maSeance(int $id): ?Seance
    {
        $s = $this->seances->find($id);
        return $s && $s->getKine() === $this->kine() ? $s : null;
    }
}
