<?php

namespace App\Controller\Api;

use App\Entity\DemandeRdv;
use App\Service\ApiSerializer;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Espace Visiteur (sans compte) : US-01, US-02, US-03. */
#[Route('/api/public')]
class PublicController extends ApiController
{
    public function __construct(ApiSerializer $serializer, private array $centre)
    {
        parent::__construct($serializer);
    }

    /** US-01 : informations générales du centre. */
    #[Route('/centre', methods: ['GET'])]
    public function centre(): JsonResponse
    {
        return $this->json($this->centre);
    }

    /** US-02 + US-03 : demande de premier RDV avec e-mail de confirmation automatique. */
    #[Route('/demandes-rdv', methods: ['POST'])]
    public function demanderRdv(Request $request, EntityManagerInterface $em, MailService $mail): JsonResponse
    {
        $data = $this->body($request);

        $nom = $this->str($data, 'nom');
        $prenom = $this->str($data, 'prenom');
        $telephone = $this->str($data, 'telephone');
        $email = $this->str($data, 'email');
        $date = $this->parseDate($data['dateSouhaitee'] ?? null);

        if (!$nom || !$prenom || !$telephone || !$email || !$date) {
            return $this->error('Nom, prénom, téléphone, e-mail et date souhaitée sont obligatoires.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Adresse e-mail invalide.');
        }
        if (!preg_match('/^[0-9 +]{8,20}$/', $telephone)) {
            return $this->error('Numéro de téléphone invalide.');
        }
        if ($date < new \DateTimeImmutable('today')) {
            return $this->error('La date souhaitée doit être dans le futur.');
        }

        $demande = (new DemandeRdv())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setTelephone($telephone)
            ->setEmail($email)
            ->setDateSouhaitee($date)
            ->setMotif($this->str($data, 'motif'));

        $em->persist($demande);
        $em->flush();

        $mailEnvoye = $mail->confirmationDemandeRdv($demande);

        return $this->json([
            'message' => $mailEnvoye
                ? 'Demande envoyée ! Un e-mail de confirmation vous a été adressé.'
                : 'Demande envoyée ! (l\'e-mail de confirmation n\'a pas pu être envoyé)',
            'demande' => $this->serializer->demandeRdv($demande),
            'mailEnvoye' => $mailEnvoye,
        ], Response::HTTP_CREATED);
    }
}
