<?php

namespace App\Controller\Api;

use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Profil de l'utilisateur connecté (tous les rôles). */
#[Route('/api/me')]
class MeController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json($this->serializer->user($this->currentUser()));
    }

    #[Route('', methods: ['PUT'])]
    public function update(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $user = $this->currentUser();
        $data = $this->body($request);

        if ($v = $this->str($data, 'firstName')) {
            $user->setFirstName($v);
        }
        if ($v = $this->str($data, 'lastName')) {
            $user->setLastName($v);
        }
        if (array_key_exists('phone', $data)) {
            $user->setPhone($this->str($data, 'phone'));
        }
        if ($user instanceof Patient) {
            if (array_key_exists('adresse', $data)) {
                $user->setAdresse($this->str($data, 'adresse'));
            }
            if (array_key_exists('dateNaissance', $data)) {
                $user->setDateNaissance($this->parseDate($data['dateNaissance']));
            }
        }

        $newPassword = $data['newPassword'] ?? null;
        if ($newPassword) {
            if (!is_string($newPassword) || strlen($newPassword) < 8) {
                return $this->error('Le nouveau mot de passe doit contenir au moins 8 caractères.');
            }
            if (!$hasher->isPasswordValid($user, (string) ($data['currentPassword'] ?? ''))) {
                return $this->error('Mot de passe actuel incorrect.');
            }
            $user->setPassword($hasher->hashPassword($user, $newPassword));
        }

        $em->flush();

        return $this->json(['message' => 'Profil mis à jour.', 'user' => $this->serializer->user($user)]);
    }
}
