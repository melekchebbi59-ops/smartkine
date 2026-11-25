<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ApiSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Contrôleur de base de l'API : petites méthodes utilitaires partagées
 * (lecture du JSON, erreurs, dates) pour garder les autres contrôleurs courts.
 */
abstract class ApiController extends AbstractController
{
    public function __construct(protected ApiSerializer $serializer)
    {
    }

    /** Corps JSON de la requête (tableau vide si absent). */
    protected function body(Request $request): array
    {
        if ($request->getContent() === '') {
            return [];
        }

        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw new BadRequestHttpException('Le format JSON est invalide.');
        }
    }

    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->json(['message' => $message], $status);
    }

    protected function notFound(string $what = 'Ressource'): JsonResponse
    {
        return $this->error($what . ' introuvable.', Response::HTTP_NOT_FOUND);
    }

    /** Convertit « 2026-11-25T14:30 » ou « 2026-11-25 » en date, ou null si invalide. */
    protected function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function str(array $data, string $key): ?string
    {
        $v = isset($data[$key]) ? trim((string) $data[$key]) : '';
        return $v === '' ? null : $v;
    }

    /** Montant positif au format « 12.50 », ou null. */
    protected function money(mixed $value): ?string
    {
        if (!is_numeric($value) || (float) $value < 0) {
            return null;
        }
        return number_format((float) $value, 2, '.', '');
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();
        return $user;
    }
}
