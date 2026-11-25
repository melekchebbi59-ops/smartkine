<?php

namespace App\Service;

use App\Entity\DemandeRdv;
use App\Entity\DossierMedical;
use App\Entity\Exercice;
use App\Entity\Facture;
use App\Entity\Kine;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Paiement;
use App\Entity\Patient;
use App\Entity\PlanReeducation;
use App\Entity\Seance;
use App\Entity\User;

/**
 * Transforme les entités en tableaux JSON simples pour le frontend.
 * Écrit à la main (plutôt qu'avec des groupes de sérialisation) pour rester lisible
 * et éviter d'exposer des données sensibles (mot de passe, dossier médical...).
 */
class ApiSerializer
{
    public static function date(?\DateTimeInterface $d): ?string
    {
        return $d?->format('Y-m-d\TH:i:s');
    }

    public function userLight(?User $u): ?array
    {
        if (!$u) {
            return null;
        }

        return [
            'id' => $u->getId(),
            'type' => $u->getType(),
            'firstName' => $u->getFirstName(),
            'lastName' => $u->getLastName(),
            'fullName' => $u->getFullName(),
        ];
    }

    public function user(User $u): array
    {
        $data = $this->userLight($u) + [
            'email' => $u->getEmail(),
            'phone' => $u->getPhone(),
            'active' => $u->isActive(),
            'roles' => $u->getRoles(),
            'createdAt' => self::date($u->getCreatedAt()),
        ];

        if ($u instanceof Kine) {
            $data['specialite'] = $u->getSpecialite();
            $data['nbPatients'] = $u->getPatients()->count();
        }

        if ($u instanceof Patient) {
            $data += $this->patientInfos($u);
        }

        return $data;
    }

    private function patientInfos(Patient $p): array
    {
        return [
            'dateNaissance' => $p->getDateNaissance()?->format('Y-m-d'),
            'adresse' => $p->getAdresse(),
            'qualification' => $p->getQualification(),
            'medecinTraitant' => $p->getMedecinTraitant(),
            'typeTraitement' => $p->getTypeTraitement(),
            'statutInscription' => $p->getStatutInscription(),
            'kine' => $this->userLight($p->getKine()),
        ];
    }

    public function demandeRdv(DemandeRdv $d): array
    {
        return [
            'id' => $d->getId(),
            'nom' => $d->getNom(),
            'prenom' => $d->getPrenom(),
            'telephone' => $d->getTelephone(),
            'email' => $d->getEmail(),
            'dateSouhaitee' => self::date($d->getDateSouhaitee()),
            'motif' => $d->getMotif(),
            'statut' => $d->getStatut(),
            'dateRdvConfirmee' => self::date($d->getDateRdvConfirmee()),
            'createdAt' => self::date($d->getCreatedAt()),
            'gerant' => $this->userLight($d->getGerant()),
        ];
    }

    public function dossier(?DossierMedical $d): ?array
    {
        if (!$d) {
            return null;
        }

        return [
            'id' => $d->getId(),
            'descriptionEtat' => $d->getDescriptionEtat(),
            'tensionArterielle' => $d->getTensionArterielle(),
            'poids' => $d->getPoids(),
            'glycemie' => $d->getGlycemie(),
            'autresMaladies' => $d->getAutresMaladies(),
            'updatedAt' => self::date($d->getUpdatedAt()),
        ];
    }

    public function exercice(Exercice $e): array
    {
        return [
            'id' => $e->getId(),
            'nom' => $e->getNom(),
            'duree' => $e->getDuree(),
            'cout' => $e->getCout(),
            'objectif' => $e->getObjectif(),
            'image' => $e->getImage(),
        ];
    }

    public function seance(Seance $s): array
    {
        return [
            'id' => $s->getId(),
            'dateHeure' => self::date($s->getDateHeure()),
            'duree' => $s->getDuree(),
            'objectif' => $s->getObjectif(),
            'statut' => $s->getStatut(),
            'dateProposee' => self::date($s->getDateProposee()),
            'motifDemande' => $s->getMotifDemande(),
            'patient' => $this->userLight($s->getPatient()),
            'kine' => $this->userLight($s->getKine()),
            'plan' => $s->getPlan() ? ['id' => $s->getPlan()->getId(), 'titre' => $s->getPlan()->getTitre()] : null,
            'exercices' => array_map(fn (Exercice $e) => $this->exercice($e), $s->getExercices()->toArray()),
            'prix' => $s->getPrix(),
            'facturee' => $s->getFacture() !== null,
        ];
    }

    public function plan(PlanReeducation $p, bool $avecSeances = true): array
    {
        $data = [
            'id' => $p->getId(),
            'titre' => $p->getTitre(),
            'objectifs' => $p->getObjectifs(),
            'dateCreation' => self::date($p->getDateCreation()),
            'patient' => $this->userLight($p->getPatient()),
            'kine' => $this->userLight($p->getKine()),
            'exercices' => array_map(fn (Exercice $e) => $this->exercice($e), $p->getExercices()->toArray()),
        ];

        if ($avecSeances) {
            $data['seances'] = array_map(fn (Seance $s) => $this->seance($s), $p->getSeances()->toArray());
        }

        return $data;
    }

    public function facture(Facture $f): array
    {
        return [
            'id' => $f->getId(),
            'numero' => $f->getNumero(),
            'dateEmission' => self::date($f->getDateEmission()),
            'montantTotal' => $f->getMontantTotal(),
            'montantPaye' => $f->getMontantPaye(),
            'resteAPayer' => $f->getResteAPayer(),
            'statut' => $f->getStatut(),
            'patient' => $this->userLight($f->getPatient()),
            'seances' => array_map(fn (Seance $s) => [
                'id' => $s->getId(),
                'dateHeure' => self::date($s->getDateHeure()),
                'statut' => $s->getStatut(),
                'prix' => $s->getPrix(),
            ], $f->getSeances()->toArray()),
            'paiements' => array_map(fn (Paiement $p) => $this->paiement($p), $f->getPaiements()->toArray()),
        ];
    }

    public function paiement(Paiement $p): array
    {
        return [
            'id' => $p->getId(),
            'montant' => $p->getMontant(),
            'datePaiement' => self::date($p->getDatePaiement()),
            'mode' => $p->getMode(),
        ];
    }

    public function message(Message $m): array
    {
        return [
            'id' => $m->getId(),
            'contenu' => $m->getContenu(),
            'dateEnvoi' => self::date($m->getDateEnvoi()),
            'lu' => $m->isLu(),
            'expediteur' => $this->userLight($m->getExpediteur()),
            'destinataire' => $this->userLight($m->getDestinataire()),
        ];
    }

    public function notification(Notification $n): array
    {
        return [
            'id' => $n->getId(),
            'texte' => $n->getTexte(),
            'lue' => $n->isLue(),
            'createdAt' => self::date($n->getCreatedAt()),
        ];
    }

    /**
     * @template T
     * @param iterable<T> $items
     */
    public function many(iterable $items, string $method): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = $this->$method($item);
        }
        return $out;
    }
}
