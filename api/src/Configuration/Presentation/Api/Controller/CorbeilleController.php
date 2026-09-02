<?php

namespace App\Configuration\Presentation\Api\Controller;

use App\Client\Application\Command\RestoreClient\RestoreClientCommand;
use App\Client\Application\Command\RestoreClient\RestoreClientHandler;
use App\Client\Application\Query\ListDeletedClients\ListDeletedClientsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/corbeille')]
#[IsGranted('configuration.settings.update')]
final class CorbeilleController extends AbstractController
{
    #[Route('/clients', name: 'api_corbeille_clients_list', methods: ['GET'])]
    public function listClients(ListDeletedClientsHandler $handler): JsonResponse
    {
        return $this->json(['items' => $handler->handle()]);
    }

    #[Route('/clients/{id}/restore', name: 'api_corbeille_clients_restore', methods: ['POST'])]
    public function restoreClient(string $id, RestoreClientHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new RestoreClientCommand($id)));
    }
}
