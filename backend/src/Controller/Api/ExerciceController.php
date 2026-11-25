<?php

namespace App\Controller\Api;

use App\Entity\Exercice;
use App\Entity\Gerant;
use App\Repository\ExerciceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Banque d'exercices.
 *  - US-19 : le kiné ajoute / modifie des exercices (nom, durée, objectif, image)
 *  - US-30 : le gérant gère la banque, y compris la tarification (coût) et la suppression
 */
#[Route('/api/exercices')]
class ExerciceController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, ExerciceRepository $repo): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('e')->orderBy('e.nom', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(e.nom) LIKE :q OR LOWER(e.objectif) LIKE :q')->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $this->json($this->serializer->many($qb->getQuery()->getResult(), 'exercice'));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $exercice = new Exercice();
        if ($erreur = $this->remplir($exercice, $this->body($request))) {
            return $this->error($erreur);
        }

        $em->persist($exercice);
        $em->flush();

        return $this->json(['message' => 'Exercice ajouté.', 'exercice' => $this->serializer->exercice($exercice)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STAFF')]
    public function update(int $id, Request $request, ExerciceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $exercice = $repo->find($id);
        if (!$exercice) {
            return $this->notFound('Exercice');
        }
        if ($erreur = $this->remplir($exercice, $this->body($request))) {
            return $this->error($erreur);
        }
        $em->flush();

        return $this->json(['message' => 'Exercice modifié.', 'exercice' => $this->serializer->exercice($exercice)]);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_GERANT')]
    public function delete(int $id, ExerciceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $exercice = $repo->find($id);
        if (!$exercice) {
            return $this->notFound('Exercice');
        }
        $em->remove($exercice);
        $em->flush();

        return $this->json(['message' => 'Exercice supprimé.']);
    }

    /** Envoi de l'image (multipart, champ « image »). */
    #[Route('/{id}/image', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STAFF')]
    public function image(int $id, Request $request, ExerciceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $exercice = $repo->find($id);
        if (!$exercice) {
            return $this->notFound('Exercice');
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->error('Aucune image reçue.');
        }

        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = $file->getMimeType();
        if (!isset($extensions[$mime]) || $file->getSize() > 3 * 1024 * 1024) {
            return $this->error('Image JPG, PNG, WEBP ou GIF de 3 Mo maximum.');
        }

        $dossier = $this->getParameter('kernel.project_dir') . '/public/uploads/exercices';
        $nom = bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $file->move($dossier, $nom);

        $exercice->setImage('/uploads/exercices/' . $nom);
        $em->flush();

        return $this->json(['message' => 'Image enregistrée.', 'exercice' => $this->serializer->exercice($exercice)]);
    }

    /** Retourne un message d'erreur ou null. Seul le gérant peut fixer le coût (tarification). */
    private function remplir(Exercice $e, array $data): ?string
    {
        $nom = $this->str($data, 'nom') ?? $e->getNom();
        $duree = isset($data['duree']) ? (int) $data['duree'] : $e->getDuree();
        if (!$nom || $duree <= 0) {
            return 'Le nom et une durée (en minutes) sont obligatoires.';
        }

        $e->setNom($nom)->setDuree($duree);
        if (array_key_exists('objectif', $data)) {
            $e->setObjectif($this->str($data, 'objectif'));
        }

        if (array_key_exists('cout', $data) && $this->currentUser() instanceof Gerant) {
            $cout = $this->money($data['cout']);
            if ($cout === null) {
                return 'Coût invalide.';
            }
            $e->setCout($cout);
        }

        return null;
    }
}
