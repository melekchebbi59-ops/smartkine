<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Entity\DemandeRdv;
use App\Entity\Gerant;
use App\Repository\DemandeRdvRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** US-23 (partie 1) : le gérant traite les demandes de premier RDV. */
#[Route('/api/gerant/demandes-rdv')]
class DemandeRdvController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, DemandeRdvRepository $repo): JsonResponse
    {
        $criteres = [];
        if ($statut = $request->query->get('statut')) {
            $criteres['statut'] = $statut;
        }

        $demandes = $repo->findBy($criteres, ['createdAt' => 'DESC']);

        return $this->json($this->serializer->many($demandes, 'demandeRdv'));
    }

    #[Route('/{id}/accepter', methods: ['POST'])]
    public function accepter(int $id, Request $request, DemandeRdvRepository $repo, EntityManagerInterface $em, MailService $mail): JsonResponse
    {
        $demande = $repo->find($id);
        if (!$demande) {
            return $this->notFound('Demande');
        }

        $data = $this->body($request);
        $date = $this->parseDate($data['dateRdv'] ?? null) ?? $demande->getDateSouhaitee();

        $demande->setStatut(DemandeRdv::STATUT_ACCEPTEE)
            ->setDateRdvConfirmee($date)
            ->setGerant($this->gerant());
        $em->flush();

        $mail->demandeRdvTraitee($demande);

        return $this->json(['message' => 'Rendez-vous confirmé, e-mail envoyé au visiteur.', 'demande' => $this->serializer->demandeRdv($demande)]);
    }

    #[Route('/{id}/refuser', methods: ['POST'])]
    public function refuser(int $id, DemandeRdvRepository $repo, EntityManagerInterface $em, MailService $mail): JsonResponse
    {
        $demande = $repo->find($id);
        if (!$demande) {
            return $this->notFound('Demande');
        }

        $demande->setStatut(DemandeRdv::STATUT_REFUSEE)->setGerant($this->gerant());
        $em->flush();

        $mail->demandeRdvTraitee($demande);

        return $this->json(['message' => 'Demande refusée.', 'demande' => $this->serializer->demandeRdv($demande)]);
    }

    private function gerant(): ?Gerant
    {
        $u = $this->currentUser();
        return $u instanceof Gerant ? $u : null;
    }
}
