<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Entity\Patient;
use App\Repository\PatientRepository;
use App\Service\MailService;
use App\Service\Notifier;
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
    public function valider(int $id, PatientRepository $repo, EntityManagerInterface $em, MailService $mail, Notifier $notifier): JsonResponse
    {
        return $this->traiter($id, Patient::STATUT_VALIDE, $repo, $em, $mail, $notifier);
    }

    #[Route('/{id}/refuser', methods: ['POST'])]
    public function refuser(int $id, PatientRepository $repo, EntityManagerInterface $em, MailService $mail, Notifier $notifier): JsonResponse
    {
        return $this->traiter($id, Patient::STATUT_REFUSE, $repo, $em, $mail, $notifier);
    }

    private function traiter(int $id, string $statut, PatientRepository $repo, EntityManagerInterface $em, MailService $mail, Notifier $notifier): JsonResponse
    {
        $patient = $repo->find($id);
        if (!$patient) {
            return $this->notFound('Patient');
        }

        $patient->setStatutInscription($statut);
        $em->flush();

        $mail->inscriptionTraitee($patient);
        if ($statut === Patient::STATUT_VALIDE) {
            $notifier->notifier($patient, 'Bienvenue sur SmartKiné ! Votre compte a été validé par le gérant.');
        }

        return $this->json([
            'message' => $statut === Patient::STATUT_VALIDE ? 'Inscription validée.' : 'Inscription refusée.',
            'patient' => $this->serializer->user($patient),
        ]);
    }
}
