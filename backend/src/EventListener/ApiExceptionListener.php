<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Toutes les erreurs de /api/* sont renvoyées en JSON (et non en page HTML). */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();

        if ($e instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse(['message' => 'Accès refusé pour votre rôle.'], 403));
            return;
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = match ($status) {
                403 => 'Accès refusé pour votre rôle.',
                404 => 'Ressource introuvable.',
                405 => 'Méthode non autorisée.',
                default => $e->getMessage() ?: 'Erreur.',
            };
            $event->setResponse(new JsonResponse(['message' => $message], $status));
            return;
        }

        $event->setResponse(new JsonResponse(['message' => 'Erreur interne du serveur.'], 500));
    }
}
