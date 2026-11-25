<?php

namespace App\Controller\Api\Kine;

use App\Controller\Api\ApiController;
use App\Entity\DossierMedical;
use App\Entity\Kine;
use App\Entity\Patient;
use App\Repository\PatientRepository;
use App\Repository\PlanReeducationRepository;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-14 : liste des patients affectés au kiné.
 * US-17 : consultation / mise à jour du dossier médical (kiné traitant uniquement).
 */
#[Route('/api/kine/patients')]
class PatientController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(PatientRepository $repo): JsonResponse
    {
        $patients = $repo->findBy(['kine' => $this->kine(), 'active' => true], ['lastName' => 'ASC']);

        return $this->json($this->serializer->many($patients, 'user'));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, PatientRepository $repo, SeanceRepository $seances, PlanReeducationRepository $plans): JsonResponse
    {
        $patient = $this->monPatient($id, $repo);
        if (!$patient) {
            return $this->notFound('Patient');
        }

        return $this->json($this->serializer->user($patient) + [
            'dossier' => $this->serializer->dossier($patient->getDossierMedical()),
            'seances' => $this->serializer->many($seances->findBy(['patient' => $patient], ['dateHeure' => 'DESC']), 'seance'),
            'plans' => array_map(fn ($p) => $this->serializer->plan($p, false), $plans->findBy(['patient' => $patient], ['dateCreation' => 'DESC'])),
        ]);
    }

    #[Route('/{id}/dossier', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function dossier(int $id, Request $request, PatientRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $patient = $this->monPatient($id, $repo);
        if (!$patient) {
            return $this->notFound('Patient');
        }

        $data = $this->body($request);
        $dossier = $patient->getDossierMedical();
        if (!$dossier) {
            $dossier = new DossierMedical();
            $patient->setDossierMedical($dossier);
            $em->persist($dossier);
        }

        foreach (['poids', 'glycemie'] as $champ) {
            if (array_key_exists($champ, $data) && $data[$champ] !== null && $data[$champ] !== '' && !is_numeric($data[$champ])) {
                return $this->error(ucfirst($champ) . ' doit être un nombre.');
            }
        }

        $dossier->setDescriptionEtat($this->str($data, 'descriptionEtat'))
            ->setTensionArterielle($this->str($data, 'tensionArterielle'))
            ->setPoids($this->str($data, 'poids'))
            ->setGlycemie($this->str($data, 'glycemie'))
            ->setAutresMaladies($this->str($data, 'autresMaladies'))
            ->touch();

        $em->flush();

        return $this->json(['message' => 'Dossier médical mis à jour.', 'dossier' => $this->serializer->dossier($dossier)]);
    }

    private function kine(): Kine
    {
        /** @var Kine $k */
        $k = $this->currentUser();
        return $k;
    }

    /** Sécurité : un kiné n'accède qu'aux patients qui lui sont affectés. */
    private function monPatient(int $id, PatientRepository $repo): ?Patient
    {
        $patient = $repo->find($id);
        return $patient && $patient->getKine() === $this->kine() ? $patient : null;
    }
}
