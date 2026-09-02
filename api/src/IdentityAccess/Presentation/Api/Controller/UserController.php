<?php

namespace App\IdentityAccess\Presentation\Api\Controller;

use App\IdentityAccess\Application\Command\CreateUser\CreateUserCommand;
use App\IdentityAccess\Application\Command\CreateUser\CreateUserHandler;
use App\IdentityAccess\Application\Command\SuspendUser\SuspendUserCommand;
use App\IdentityAccess\Application\Command\SuspendUser\SuspendUserHandler;
use App\IdentityAccess\Application\Command\UpdateUser\UpdateUserCommand;
use App\IdentityAccess\Application\Command\UpdateUser\UpdateUserHandler;
use App\IdentityAccess\Application\Query\GetUser\GetUserHandler;
use App\IdentityAccess\Application\Query\GetUser\GetUserQuery;
use App\IdentityAccess\Application\Query\ListUsers\ListUsersHandler;
use App\IdentityAccess\Application\Query\ListUsers\ListUsersQuery;
use App\IdentityAccess\Domain\Enum\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
final class UserController extends AbstractController
{
    #[Route('', name: 'api_users_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(ListUsersHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListUsersQuery()));
    }

    #[Route('', name: 'api_users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, CreateUserHandler $handler): JsonResponse
    {
        $data = $request->toArray();

        $result = $handler->handle(new CreateUserCommand(
            prenom: $data['prenom'] ?? '',
            nom: $data['nom'] ?? '',
            telephone: $data['telephone'] ?? '',
            login: $data['login'] ?? '',
            password: $data['password'] ?? '',
            role: Role::from($data['role'] ?? Role::AGENT->value),
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_users_get', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function get(string $id, GetUserHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetUserQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_users_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, Request $request, UpdateUserHandler $handler): JsonResponse
    {
        $data = $request->toArray();

        $result = $handler->handle(new UpdateUserCommand(
            id: $id,
            prenom: $data['prenom'] ?? null,
            nom: $data['nom'] ?? null,
            telephone: $data['telephone'] ?? null,
            login: $data['login'] ?? null,
            password: $data['password'] ?? null,
            role: isset($data['role']) ? Role::from($data['role']) : null,
            isActive: isset($data['isActive']) ? (bool) $data['isActive'] : null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}/suspend', name: 'api_users_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function suspend(string $id, SuspendUserHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new SuspendUserCommand($id))->toArray());
    }
}
