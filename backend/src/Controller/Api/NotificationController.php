<?php

namespace App\Controller\Api;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** US-12 : notifications de l'utilisateur connecté. */
#[Route('/api/notifications')]
class NotificationController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(NotificationRepository $repo): JsonResponse
    {
        $notifs = $repo->findBy(['destinataire' => $this->currentUser()], ['createdAt' => 'DESC', 'id' => 'DESC'], 30);

        return $this->json([
            'nonLues' => $repo->count(['destinataire' => $this->currentUser(), 'lue' => false]),
            'notifications' => $this->serializer->many($notifs, 'notification'),
        ]);
    }

    #[Route('/lire-tout', methods: ['POST'])]
    public function lireTout(NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        foreach ($repo->findBy(['destinataire' => $this->currentUser(), 'lue' => false]) as $n) {
            $n->setLue(true);
        }
        $em->flush();

        return $this->json(['message' => 'Notifications lues.']);
    }

    #[Route('/{id}/lue', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function lue(int $id, NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $n = $repo->find($id);
        if (!$n || $n->getDestinataire() !== $this->currentUser()) {
            return $this->notFound('Notification');
        }
        $n->setLue(true);
        $em->flush();

        return $this->json(['message' => 'OK']);
    }
}
