<?php

namespace App\Controller\Api;

use App\Entity\Gerant;
use App\Entity\Kine;
use App\Entity\Message;
use App\Entity\Patient;
use App\Entity\User;
use App\Repository\KineRepository;
use App\Repository\MessageRepository;
use App\Repository\PatientRepository;
use App\Repository\UserRepository;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messagerie interne : US-11 (patient), US-20 (kiné), US-29 (gérant, y compris diffusion).
 * Règles : patient <-> gérant ou son kiné ; kiné <-> gérant, autres kinés, ses patients ; gérant <-> tout le monde.
 */
#[Route('/api/messages')]
class MessageController extends ApiController
{
    /** Liste des conversations (dernier message + nombre de non-lus). */
    #[Route('', methods: ['GET'])]
    public function conversations(MessageRepository $repo): JsonResponse
    {
        $moi = $this->currentUser();
        $convs = [];

        foreach ($repo->deUtilisateur($moi) as $m) {
            $contact = $m->getExpediteur() === $moi ? $m->getDestinataire() : $m->getExpediteur();
            $id = $contact->getId();
            if (!isset($convs[$id])) {
                $convs[$id] = ['contact' => $this->serializer->userLight($contact), 'dernierMessage' => $this->serializer->message($m), 'nonLus' => 0];
            }
            if ($m->getDestinataire() === $moi && !$m->isLu()) {
                $convs[$id]['nonLus']++;
            }
        }

        return $this->json(array_values($convs));
    }

    /** Personnes à qui l'utilisateur peut écrire. */
    #[Route('/contacts', methods: ['GET'])]
    public function contacts(UserRepository $users, PatientRepository $patients, KineRepository $kines): JsonResponse
    {
        return $this->json(array_map(
            fn (User $u) => $this->serializer->userLight($u),
            $this->contactsAutorises($users, $patients, $kines)
        ));
    }

    #[Route('/nonlus', methods: ['GET'])]
    public function nonLus(MessageRepository $repo): JsonResponse
    {
        return $this->json(['count' => $repo->count(['destinataire' => $this->currentUser(), 'lu' => false])]);
    }

    /** Fil de discussion avec un contact (marque les messages reçus comme lus). */
    #[Route('/avec/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function fil(int $id, UserRepository $users, MessageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $contact = $users->find($id);
        if (!$contact) {
            return $this->notFound('Contact');
        }

        $moi = $this->currentUser();
        $messages = $repo->conversation($moi, $contact);
        foreach ($messages as $m) {
            if ($m->getDestinataire() === $moi && !$m->isLu()) {
                $m->setLu(true);
            }
        }
        $em->flush();

        return $this->json([
            'contact' => $this->serializer->userLight($contact),
            'messages' => $this->serializer->many($messages, 'message'),
        ]);
    }

    /** { destinataireId, contenu } */
    #[Route('', methods: ['POST'])]
    public function envoyer(Request $request, UserRepository $users, PatientRepository $patients, KineRepository $kines, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        $contenu = $this->str($data, 'contenu');
        $dest = $users->find((int) ($data['destinataireId'] ?? 0));

        if (!$contenu || !$dest) {
            return $this->error('Destinataire et message obligatoires.');
        }
        if (mb_strlen($contenu) > 2000) {
            return $this->error('Message trop long (2000 caractères max.).');
        }
        if (!in_array($dest, $this->contactsAutorises($users, $patients, $kines), true)) {
            return $this->error("Vous ne pouvez pas écrire à cette personne.", Response::HTTP_FORBIDDEN);
        }

        $m = (new Message())->setExpediteur($this->currentUser())->setDestinataire($dest)->setContenu($contenu);
        $em->persist($m);
        $em->flush();

        return $this->json(['message' => 'Message envoyé.', 'data' => $this->serializer->message($m)], Response::HTTP_CREATED);
    }

    /** US-29 : diffusion du gérant. { cible: kines|patients|tous, contenu } */
    #[Route('/diffusion', methods: ['POST'])]
    #[IsGranted('ROLE_GERANT')]
    public function diffusion(Request $request, KineRepository $kines, PatientRepository $patients, EntityManagerInterface $em, Notifier $notifier): JsonResponse
    {
        $data = $this->body($request);
        $contenu = $this->str($data, 'contenu');
        $cible = $data['cible'] ?? 'tous';

        if (!$contenu) {
            return $this->error('Le message est obligatoire.');
        }

        $destinataires = [];
        if (in_array($cible, ['kines', 'tous'], true)) {
            $destinataires = array_merge($destinataires, $kines->findBy(['active' => true]));
        }
        if (in_array($cible, ['patients', 'tous'], true)) {
            $destinataires = array_merge($destinataires, $patients->findBy(['active' => true, 'statutInscription' => Patient::STATUT_VALIDE]));
        }

        $moi = $this->currentUser();
        foreach ($destinataires as $d) {
            $em->persist((new Message())->setExpediteur($moi)->setDestinataire($d)->setContenu($contenu));
        }
        $em->flush();

        foreach ($destinataires as $d) {
            $notifier->notifier($d, 'Nouveau message du gérant : ' . mb_substr($contenu, 0, 80) . (mb_strlen($contenu) > 80 ? '…' : ''));
        }

        return $this->json(['message' => sprintf('Message diffusé à %d personne(s).', count($destinataires))], Response::HTTP_CREATED);
    }

    /** @return User[] */
    private function contactsAutorises(UserRepository $users, PatientRepository $patients, KineRepository $kines): array
    {
        $moi = $this->currentUser();
        $gerants = $users->createQueryBuilder('u')->andWhere('u INSTANCE OF ' . Gerant::class)->andWhere('u.active = true')->getQuery()->getResult();

        if ($moi instanceof Gerant) {
            $liste = $users->findBy(['active' => true], ['lastName' => 'ASC']);
            $liste = array_filter($liste, fn (User $u) => !($u instanceof Patient && !$u->isValide()));
        } elseif ($moi instanceof Kine) {
            $liste = array_merge($gerants, $kines->findBy(['active' => true]), $patients->findBy(['kine' => $moi, 'active' => true]));
        } else {
            /** @var Patient $moi */
            $liste = $gerants;
            if ($moi->getKine()) {
                $liste[] = $moi->getKine();
            }
        }

        $unique = [];
        foreach ($liste as $u) {
            if ($u !== $moi) {
                $unique[$u->getId()] = $u;
            }
        }

        return array_values($unique);
    }
}
