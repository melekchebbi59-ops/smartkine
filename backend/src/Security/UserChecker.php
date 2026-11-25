<?php

namespace App\Security;

use App\Entity\Patient;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion :
 *  - des comptes désactivés par le gérant (US-22) ;
 *  - des patients dont l'inscription n'est pas encore validée (US-04 / US-23).
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Votre compte a été désactivé. Contactez le centre.');
        }

        if ($user instanceof Patient && $user->getStatutInscription() === Patient::STATUT_EN_ATTENTE) {
            throw new CustomUserMessageAccountStatusException("Votre inscription est en attente de validation par le gérant.");
        }

        if ($user instanceof Patient && $user->getStatutInscription() === Patient::STATUT_REFUSE) {
            throw new CustomUserMessageAccountStatusException("Votre inscription a été refusée. Contactez le centre.");
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
