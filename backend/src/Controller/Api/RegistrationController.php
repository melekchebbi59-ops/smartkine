<?php

namespace App\Controller\Api;

use App\Entity\DemandeRdv;
use App\Entity\Patient;
use App\Repository\DemandeRdvRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US-04 : le patient crée son compte APRÈS son 1er RDV.
 * Corrections Sprint 1 :
 *  - le compte est créé « en attente » et doit être validé par le gérant (US-23) ;
 *  - l'e-mail doit correspondre à une demande de RDV acceptée.
 */
class RegistrationController extends ApiController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        DemandeRdvRepository $demandeRdvRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = $this->body($request);

        $firstName = $this->str($data, 'firstName');
        $lastName = $this->str($data, 'lastName');
        $email = strtolower($this->str($data, 'email') ?? '');
        $password = $data['password'] ?? '';

        if (!$firstName || !$lastName || $email === '' || !is_string($password) || strlen($password) < 8) {
            return $this->error('Veuillez remplir les champs obligatoires. Le mot de passe doit contenir au moins 8 caractères.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Adresse e-mail invalide.');
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->error('Cette adresse e-mail est déjà utilisée.', Response::HTTP_CONFLICT);
        }

        $demande = $demandeRdvRepository->findOneBy(['email' => $email, 'statut' => DemandeRdv::STATUT_ACCEPTEE]);
        if (!$demande) {
            return $this->error(
                "Aucun premier rendez-vous confirmé n'a été trouvé pour cette adresse e-mail. "
                . "Utilisez l'adresse de votre demande de rendez-vous.",
                Response::HTTP_FORBIDDEN
            );
        }

        $patient = new Patient();
        $patient->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setPhone($this->str($data, 'phone') ?? $demande->getTelephone())
            ->setDateNaissance($this->parseDate($data['dateNaissance'] ?? null))
            ->setAdresse($this->str($data, 'adresse'))
            ->setQualification($this->str($data, 'qualification'))
            ->setMedecinTraitant($this->str($data, 'medecinTraitant'))
            ->setTypeTraitement($this->str($data, 'typeTraitement') ?? $demande->getMotif())
            ->setStatutInscription(Patient::STATUT_EN_ATTENTE);

        $patient->setPassword($passwordHasher->hashPassword($patient, $password));

        $entityManager->persist($patient);
        $entityManager->flush();

        return $this->json([
            'message' => 'Compte créé ! Il sera actif dès sa validation par le gérant du centre.',
            'user' => $this->serializer->user($patient),
        ], Response::HTTP_CREATED);
    }
}
