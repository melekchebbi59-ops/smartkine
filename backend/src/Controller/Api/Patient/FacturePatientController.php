<?php

namespace App\Controller\Api\Patient;

use App\Controller\Api\ApiController;
use App\Repository\FactureRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** US-10 : le patient consulte ses factures, paiements et reste à payer. */
#[Route('/api/patient/factures')]
class FacturePatientController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(FactureRepository $repo): JsonResponse
    {
        $factures = $repo->findBy(['patient' => $this->currentUser()], ['dateEmission' => 'DESC']);

        $total = 0.0;
        $paye = 0.0;
        $reste = 0.0;
        foreach ($factures as $f) {
            $total += (float) $f->getMontantTotal();
            $paye += (float) $f->getMontantPaye();
            $reste += (float) $f->getResteAPayer();
        }

        return $this->json([
            'resume' => [
                'total' => number_format($total, 2, '.', ''),
                'paye' => number_format($paye, 2, '.', ''),
                'reste' => number_format($reste, 2, '.', ''),
            ],
            'factures' => $this->serializer->many($factures, 'facture'),
        ]);
    }
}
