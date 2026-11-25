<?php

namespace App\Controller\Api\Kine;

use App\Controller\Api\ApiController;
use App\Entity\Kine;
use App\Entity\PlanReeducation;
use App\Repository\ExerciceRepository;
use App\Repository\PatientRepository;
use App\Repository\PlanReeducationRepository;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** US-18 : plans de rééducation personnalisés à partir de la banque d'exercices. */
#[Route('/api/kine/plans')]
class PlanController extends ApiController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, PlanReeducationRepository $repo, PatientRepository $patients): JsonResponse
    {
        $criteres = ['kine' => $this->kine()];
        if ($pid = $request->query->getInt('patientId')) {
            $criteres['patient'] = $patients->find($pid);
        }

        $plans = $repo->findBy($criteres, ['dateCreation' => 'DESC']);

        return $this->json(array_map(fn ($p) => $this->serializer->plan($p), $plans));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, PlanReeducationRepository $repo): JsonResponse
    {
        $plan = $this->monPlan($id, $repo);
        return $plan ? $this->json($this->serializer->plan($plan)) : $this->notFound('Plan');
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, PatientRepository $patients, ExerciceRepository $exercices, EntityManagerInterface $em, Notifier $notifier): JsonResponse
    {
        $data = $this->body($request);
        $patient = $patients->find((int) ($data['patientId'] ?? 0));
        if (!$patient || $patient->getKine() !== $this->kine()) {
            return $this->error('Patient introuvable ou non affecté à vous.');
        }

        $titre = $this->str($data, 'titre');
        $ids = array_map('intval', (array) ($data['exerciceIds'] ?? []));
        if (!$titre || !$ids) {
            return $this->error('Un titre et au moins un exercice sont obligatoires.');
        }

        $plan = (new PlanReeducation())
            ->setTitre($titre)
            ->setObjectifs($this->str($data, 'objectifs'))
            ->setPatient($patient)
            ->setKine($this->kine())
            ->replaceExercices($exercices->findBy(['id' => $ids]));

        $em->persist($plan);
        $em->flush();
        $notifier->notifier($patient, sprintf('Votre kiné a créé un nouveau plan de rééducation : « %s ».', $titre));

        return $this->json(['message' => 'Plan créé.', 'plan' => $this->serializer->plan($plan)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, PlanReeducationRepository $repo, ExerciceRepository $exercices, EntityManagerInterface $em): JsonResponse
    {
        $plan = $this->monPlan($id, $repo);
        if (!$plan) {
            return $this->notFound('Plan');
        }

        $data = $this->body($request);
        if ($t = $this->str($data, 'titre')) {
            $plan->setTitre($t);
        }
        if (array_key_exists('objectifs', $data)) {
            $plan->setObjectifs($this->str($data, 'objectifs'));
        }
        if (array_key_exists('exerciceIds', $data)) {
            $ids = array_map('intval', (array) $data['exerciceIds']);
            if (!$ids) {
                return $this->error('Le plan doit contenir au moins un exercice.');
            }
            $plan->replaceExercices($exercices->findBy(['id' => $ids]));
        }

        $em->flush();

        return $this->json(['message' => 'Plan modifié.', 'plan' => $this->serializer->plan($plan)]);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, PlanReeducationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $plan = $this->monPlan($id, $repo);
        if (!$plan) {
            return $this->notFound('Plan');
        }
        $em->remove($plan);
        $em->flush();

        return $this->json(['message' => 'Plan supprimé.']);
    }

    private function kine(): Kine
    {
        /** @var Kine $k */
        $k = $this->currentUser();
        return $k;
    }

    private function monPlan(int $id, PlanReeducationRepository $repo): ?PlanReeducation
    {
        $p = $repo->find($id);
        return $p && $p->getKine() === $this->kine() ? $p : null;
    }
}
