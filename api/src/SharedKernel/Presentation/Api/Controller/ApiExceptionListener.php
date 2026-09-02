<?php

namespace App\SharedKernel\Presentation\Api\Controller;

use App\SharedKernel\Domain\Exception\DomainException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: 'kernel.exception', priority: 10)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();
        if ($e instanceof DomainException) {
            $event->setResponse(new JsonResponse(['error' => $e->getMessage()], 422));

            return;
        }

        if ($e instanceof \InvalidArgumentException) {
            $event->setResponse(new JsonResponse(['error' => $e->getMessage()], 400));

            return;
        }

        if ($e instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse([
                'error' => 'Accès refusé : vous n\'avez pas la permission d\'effectuer cette action.',
            ], 403));

            return;
        }

        if ($e instanceof HttpExceptionInterface) {
            $message = $e->getMessage();
            if (403 === $e->getStatusCode() && ($message === '' || 0 === strcasecmp($message, 'Access Denied.'))) {
                $message = 'Accès refusé : vous n\'avez pas la permission d\'effectuer cette action.';
            }
            $event->setResponse(new JsonResponse(['error' => $message], $e->getStatusCode()));
        }
    }
}
