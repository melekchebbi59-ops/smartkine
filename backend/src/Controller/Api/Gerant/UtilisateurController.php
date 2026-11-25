<?php

namespace App\Controller\Api\Gerant;

use App\Controller\Api\ApiController;
use App\Entity\Gerant;
use App\Entity\Kine;
use App\Entity\Patient;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/** US-22 : le gérant crée les comptes kinés et gère tous les utilisateurs. */
#[Route('/api/gerant/utilisateurs')]
class UtilisateurController extends ApiController
{
    /** Liste filtrable : ?type=kine|patient|gerant */
    #[Route('', methods: ['GET'])]
    public function index(Request $request, UserRepository $repo): JsonResponse
    {
        $classes = ['kine' => Kine::class, 'patient' => Patient::class, 'gerant' => Gerant::class];
        $type = $request->query->get('type');

        $qb = $repo->createQueryBuilder('u')->orderBy('u.lastName', 'ASC');
        if ($type && isset($classes[$type])) {
            $qb->andWhere('u INSTANCE OF ' . $classes[$type]);
        }

        return $this->json($this->serializer->many($qb->getQuery()->getResult(), 'user'));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, UserRepository $repo): JsonResponse
    {
        $user = $repo->find($id);
        return $user ? $this->json($this->serializer->user($user)) : $this->notFound('Utilisateur');
    }

    /** Création d'un compte kiné (ou gérant) : { type, firstName, lastName, email, password, phone, specialite } */
    #[Route('', methods: ['POST'])]
    public function create(Request $request, UserRepository $repo, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $data = $this->body($request);
        $type = $data['type'] ?? 'kine';

        $firstName = $this->str($data, 'firstName');
        $lastName = $this->str($data, 'lastName');
        $email = strtolower($this->str($data, 'email') ?? '');
        $password = (string) ($data['password'] ?? '');

        if (!in_array($type, ['kine', 'gerant'], true)) {
            return $this->error('Le gérant crée uniquement des comptes kiné ou gérant (les patients s\'inscrivent eux-mêmes).');
        }
        if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return $this->error('Prénom, nom, e-mail valide et mot de passe (8 caractères min.) sont obligatoires.');
        }
        if ($repo->findOneBy(['email' => $email])) {
            return $this->error('Cette adresse e-mail est déjà utilisée.', Response::HTTP_CONFLICT);
        }

        $user = $type === 'kine' ? (new Kine())->setSpecialite($this->str($data, 'specialite')) : new Gerant();
        $user->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setPhone($this->str($data, 'phone'));
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'Compte créé.', 'user' => $this->serializer->user($user)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, UserRepository $repo, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $user = $repo->find($id);
        if (!$user) {
            return $this->notFound('Utilisateur');
        }

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
        if ($v = $this->str($data, 'email')) {
            $v = strtolower($v);
            $existant = $repo->findOneBy(['email' => $v]);
            if ($existant && $existant->getId() !== $user->getId()) {
                return $this->error('Cette adresse e-mail est déjà utilisée.', Response::HTTP_CONFLICT);
            }
            $user->setEmail($v);
        }
        if ($user instanceof Kine && array_key_exists('specialite', $data)) {
            $user->setSpecialite($this->str($data, 'specialite'));
        }
        if ($user instanceof Patient) {
            foreach (['adresse', 'qualification', 'medecinTraitant', 'typeTraitement'] as $champ) {
                if (array_key_exists($champ, $data)) {
                    $user->{'set' . ucfirst($champ)}($this->str($data, $champ));
                }
            }
            if (array_key_exists('dateNaissance', $data)) {
                $user->setDateNaissance($this->parseDate($data['dateNaissance']));
            }
        }
        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 8) {
                return $this->error('Le mot de passe doit contenir au moins 8 caractères.');
            }
            $user->setPassword($hasher->hashPassword($user, (string) $data['password']));
        }

        $em->flush();

        return $this->json(['message' => 'Utilisateur modifié.', 'user' => $this->serializer->user($user)]);
    }

    /** Activer / désactiver un compte : { active: true|false } */
    #[Route('/{id}/statut', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function statut(int $id, Request $request, UserRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $repo->find($id);
        if (!$user) {
            return $this->notFound('Utilisateur');
        }
        if ($user === $this->currentUser()) {
            return $this->error('Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->setActive((bool) ($this->body($request)['active'] ?? false));
        $em->flush();

        return $this->json([
            'message' => $user->isActive() ? 'Compte activé.' : 'Compte désactivé.',
            'user' => $this->serializer->user($user),
        ]);
    }
}
