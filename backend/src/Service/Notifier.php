<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Crée les notifications internes (US-12) : confirmation, report, validation de compte... */
class Notifier
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function notifier(?User $destinataire, string $texte): void
    {
        if (!$destinataire) {
            return;
        }

        $n = (new Notification())
            ->setDestinataire($destinataire)
            ->setTexte(mb_substr($texte, 0, 255));

        $this->em->persist($n);
        $this->em->flush();
    }
}
