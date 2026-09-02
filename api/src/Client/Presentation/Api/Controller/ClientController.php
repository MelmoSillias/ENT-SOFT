<?php

namespace App\Client\Presentation\Api\Controller;

use App\Client\Application\Command\CreateClient\CreateClientCommand;
use App\Client\Application\Command\CreateClient\CreateClientHandler;
use App\Client\Application\Command\CreateClientComment\CreateClientCommentCommand;
use App\Client\Application\Command\CreateClientComment\CreateClientCommentHandler;
use App\Client\Application\Command\DeleteClient\DeleteClientCommand;
use App\Client\Application\Command\DeleteClient\DeleteClientHandler;
use App\Client\Application\Command\UpdateClient\UpdateClientCommand;
use App\Client\Application\Command\UpdateClient\UpdateClientHandler;
use App\Client\Application\Query\GetClient\GetClientHandler;
use App\Client\Application\Query\GetClient\GetClientQuery;
use App\Client\Application\Query\GetClientDetail\GetClientDetailHandler;
use App\Client\Application\Query\GetClientDetail\GetClientDetailQuery;
use App\Client\Application\Query\ListClients\ListClientsHandler;
use App\Client\Application\Query\ListClients\ListClientsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    #[IsGranted('client.clients.view')]
    public function list(Request $request, ListClientsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListClientsQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_clients_create', methods: ['POST'])]
    #[IsGranted('client.clients.create')]
    public function create(Request $request, CreateClientHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateClientCommand(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_clients_get', methods: ['GET'])]
    #[IsGranted('client.clients.view')]
    public function get(string $id, GetClientHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetClientQuery($id))->toArray());
    }

    #[Route('/{id}/detail', name: 'api_clients_detail', methods: ['GET'])]
    #[IsGranted('client.clients.view')]
    public function detail(string $id, GetClientDetailHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetClientDetailQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_clients_update', methods: ['PUT'])]
    #[IsGranted('client.clients.update')]
    public function update(string $id, Request $request, UpdateClientHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateClientCommand(
            id: $id,
            title: $data['title'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_clients_delete', methods: ['DELETE'])]
    #[IsGranted('client.clients.delete')]
    public function delete(string $id, DeleteClientHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteClientCommand($id))->toArray());
    }

    #[Route('/{id}/comments', name: 'api_clients_comments_create', methods: ['POST'])]
    #[IsGranted('client.comments.create')]
    public function createComment(string $id, Request $request, CreateClientCommentHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateClientCommentCommand(
            clientId: $id,
            content: $data['content'] ?? '',
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }
}
