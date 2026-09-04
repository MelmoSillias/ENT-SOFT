<?php

namespace App\AccessAudit\Presentation\Api\Controller;

use App\AccessAudit\Application\Command\CreateAppRole\CreateAppRoleCommand;
use App\AccessAudit\Application\Command\CreateAppRole\CreateAppRoleHandler;
use App\AccessAudit\Application\Command\DeleteAppRole\DeleteAppRoleCommand;
use App\AccessAudit\Application\Command\DeleteAppRole\DeleteAppRoleHandler;
use App\AccessAudit\Application\Command\UpdateAppRole\UpdateAppRoleCommand;
use App\AccessAudit\Application\Command\UpdateAppRole\UpdateAppRoleHandler;
use App\AccessAudit\Application\Query\GetAppRole\GetAppRoleHandler;
use App\AccessAudit\Application\Query\GetAppRole\GetAppRoleQuery;
use App\AccessAudit\Application\Query\ListAppRoles\ListAppRolesHandler;
use App\AccessAudit\Application\Query\ListAppRoles\ListAppRolesQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/roles')]
final class AppRoleController extends AbstractController
{
    #[Route('', name: 'api_roles_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(Request $request, ListAppRolesHandler $handler): JsonResponse
    {
        $enabledOnly = filter_var($request->query->get('enabledOnly', 'false'), FILTER_VALIDATE_BOOLEAN);

        return $this->json($handler->handle(new ListAppRolesQuery($enabledOnly)));
    }

    #[Route('', name: 'api_roles_create', methods: ['POST'])]
    #[IsGranted('access.roles.manage')]
    public function create(Request $request, CreateAppRoleHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateAppRoleCommand(
            code: $data['code'] ?? '',
            libelle: $data['libelle'] ?? '',
            permissionCodes: isset($data['permissions']) && is_array($data['permissions']) ? $data['permissions'] : [],
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_roles_get', methods: ['GET'])]
    #[IsGranted('access.roles.manage')]
    public function get(string $id, GetAppRoleHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetAppRoleQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_roles_update', methods: ['PUT'])]
    #[IsGranted('access.roles.manage')]
    public function update(string $id, Request $request, UpdateAppRoleHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateAppRoleCommand(
            id: $id,
            libelle: $data['libelle'] ?? null,
            permissionCodes: isset($data['permissions']) && is_array($data['permissions']) ? $data['permissions'] : null,
            hasPermissionCodes: array_key_exists('permissions', $data),
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_roles_delete', methods: ['DELETE'])]
    #[IsGranted('access.roles.manage')]
    public function delete(string $id, DeleteAppRoleHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteAppRoleCommand($id))->toArray());
    }
}
