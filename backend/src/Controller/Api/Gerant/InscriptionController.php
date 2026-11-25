<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Entity\Patient;
use App\Repository\PatientRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** US-23 (partie 2) : le gérant valide ou refuse les inscriptions des patients. */
#[Route('/api/gerant/inscriptions')]
class InscriptionController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, PatientRepository $repo): JsonResponse
    {
        $statut = $request->query->get('statut', Patient::STATUT_EN_ATTENTE);
        $patients = $repo->findBy(['statutInscription' => $statut], ['createdAt' => 'DESC']);

        return $this->json($this->serializer->many($patients, 'user'));
    }

    #[Route('/{id}/valider', methods: ['POST'])]
    public function valider(int $id, PatientRepository $repo, EntityManagerInterface $em, MailService $mail): JsonResponse
    {
        return $this->traiter($id, Patient::STATUT_VALIDE, $repo, $em, $mail);
    }

    #[Route('/{id}/refuser', methods: ['POST'])]
    public function refuser(int $id, PatientRepository $repo, EntityManagerInterface $em, MailService $mail): JsonResponse
    {
        return $this->traiter($id, Patient::STATUT_REFUSE, $repo, $em, $mail);
    }

    private function traiter(int $id, string $statut, PatientRepository $repo, EntityManagerInterface $em, MailService $mail): JsonResponse
    {
        $patient = $repo->find($id);
        if (!$patient) {
            return $this->notFound('Patient');
        }

        $patient->setStatutInscription($statut);
        $em->flush();

        $mail->inscriptionTraitee($patient);

        return $this->json([
            'message' => $statut === Patient::STATUT_VALIDE ? 'Inscription validée.' : 'Inscription refusée.',
            'patient' => $this->serializer->user($patient),
        ]);
    }
}
