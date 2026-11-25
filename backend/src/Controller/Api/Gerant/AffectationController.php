<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Repository\KineRepository;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** US-24 : le gérant affecte chaque patient validé à un kiné adapté. */
#[Route('/api/gerant')]
class AffectationController extends ApiController
{
    /** Liste des kinés actifs (pour les listes déroulantes). */
    #[Route('/kines', methods: ['GET'])]
    public function kines(KineRepository $repo): JsonResponse
    {
        return $this->json($this->serializer->many($repo->findBy(['active' => true], ['lastName' => 'ASC']), 'user'));
    }

    /** Patients validés, avec leur kiné éventuel. ?sansKine=1 pour ceux à affecter. */
    #[Route('/patients', methods: ['GET'])]
    public function patients(Request $request, PatientRepository $repo): JsonResponse
    {
        $qb = $repo->createQueryBuilder('p')
            ->andWhere('p.statutInscription = :v')->setParameter('v', 'valide')
            ->orderBy('p.lastName', 'ASC');

        if ($request->query->getBoolean('sansKine')) {
            $qb->andWhere('p.kine IS NULL');
        }

        return $this->json($this->serializer->many($qb->getQuery()->getResult(), 'user'));
    }

    /** Fiche patient complète pour le gérant (le dossier médical lui est accessible). */
    #[Route('/patients/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function patient(int $id, PatientRepository $repo): JsonResponse
    {
        $patient = $repo->find($id);
        if (!$patient) {
            return $this->notFound('Patient');
        }

        return $this->json($this->serializer->user($patient) + ['dossier' => $this->serializer->dossier($patient->getDossierMedical())]);
    }

    /** { kineId: 3 } (ou null pour retirer l'affectation) */
    #[Route('/patients/{id}/affecter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function affecter(int $id, Request $request, PatientRepository $patients, KineRepository $kines, EntityManagerInterface $em): JsonResponse
    {
        $patient = $patients->find($id);
        if (!$patient) {
            return $this->notFound('Patient');
        }
        if (!$patient->isValide()) {
            return $this->error("L'inscription de ce patient n'est pas encore validée.");
        }

        $kineId = $this->body($request)['kineId'] ?? null;
        $kine = $kineId ? $kines->find((int) $kineId) : null;
        if ($kineId && (!$kine || !$kine->isActive())) {
            return $this->notFound('Kiné');
        }

        $patient->setKine($kine);
        $em->flush();

        return $this->json([
            'message' => $kine ? sprintf('%s est affecté(e) à %s.', $patient->getFullName(), $kine->getFullName()) : 'Affectation retirée.',
            'patient' => $this->serializer->user($patient),
        ]);
    }
}
