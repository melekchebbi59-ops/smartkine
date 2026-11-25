<?php

namespace App\Service;

use App\Entity\DemandeRdv;
use App\Entity\Patient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** Envoi des e-mails automatiques (US-03 et validation de compte). */
class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom,
        private string $frontendUrl,
    ) {
    }

    public function confirmationDemandeRdv(DemandeRdv $demande): bool
    {
        $date = $demande->getDateSouhaitee()?->format('d/m/Y à H:i');

        return $this->envoyer(
            $demande->getEmail(),
            'SmartKiné — Votre demande de rendez-vous a bien été reçue',
            sprintf(
                "<p>Bonjour %s %s,</p>
                <p>Nous avons bien reçu votre demande de premier rendez-vous pour le <strong>%s</strong>.</p>
                <p>Le gérant du centre va l'étudier et vous recontactera rapidement pour la confirmer.</p>
                <p>Référence de la demande : <strong>#%d</strong></p>
                <p>À bientôt,<br>L'équipe SmartKiné</p>",
                htmlspecialchars($demande->getPrenom()),
                htmlspecialchars($demande->getNom()),
                $date,
                $demande->getId()
            )
        );
    }

    public function demandeRdvTraitee(DemandeRdv $demande): bool
    {
        if ($demande->getStatut() === DemandeRdv::STATUT_ACCEPTEE) {
            $date = ($demande->getDateRdvConfirmee() ?? $demande->getDateSouhaitee())->format('d/m/Y à H:i');
            $corps = sprintf(
                "<p>Bonjour %s,</p>
                <p>Votre premier rendez-vous est <strong>confirmé le %s</strong>.</p>
                <p>Après ce rendez-vous, vous pourrez créer votre compte patient avec cette adresse e-mail :
                <a href=\"%s/inscription\">%s/inscription</a></p>
                <p>L'équipe SmartKiné</p>",
                htmlspecialchars($demande->getPrenom()),
                $date,
                $this->frontendUrl,
                $this->frontendUrl
            );
            return $this->envoyer($demande->getEmail(), 'SmartKiné — Rendez-vous confirmé', $corps);
        }

        $corps = sprintf(
            "<p>Bonjour %s,</p><p>Nous ne pouvons malheureusement pas donner suite à votre demande de rendez-vous.
            N'hésitez pas à nous appeler pour trouver un autre créneau.</p><p>L'équipe SmartKiné</p>",
            htmlspecialchars($demande->getPrenom())
        );
        return $this->envoyer($demande->getEmail(), 'SmartKiné — Votre demande de rendez-vous', $corps);
    }

    public function inscriptionTraitee(Patient $patient): bool
    {
        $ok = $patient->isValide();
        $corps = $ok
            ? sprintf("<p>Bonjour %s,</p><p>Votre compte patient a été <strong>validé</strong>. Vous pouvez vous connecter :
                <a href=\"%s/connexion\">%s/connexion</a></p><p>L'équipe SmartKiné</p>",
                htmlspecialchars($patient->getFirstName()), $this->frontendUrl, $this->frontendUrl)
            : sprintf("<p>Bonjour %s,</p><p>Votre demande d'inscription n'a pas été validée. Contactez le centre pour plus d'informations.</p>",
                htmlspecialchars($patient->getFirstName()));

        return $this->envoyer($patient->getEmail(), $ok ? 'SmartKiné — Compte validé' : 'SmartKiné — Inscription', $corps);
    }

    /** Retourne false si l'envoi échoue (l'appli continue de fonctionner). */
    private function envoyer(string $to, string $sujet, string $html): bool
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($to)
                    ->subject($sujet)
                    ->html($html)
            );
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Envoi e-mail impossible : ' . $e->getMessage());
            return false;
        }
    }
}
