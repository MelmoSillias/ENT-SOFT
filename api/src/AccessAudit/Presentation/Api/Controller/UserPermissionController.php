<?php

namespace App\AccessAudit\Presentation\Api\Controller;

use App\AccessAudit\Domain\Entity\UtilisateurPermission;
use App\AccessAudit\Domain\PermissionCatalog;
use App\AccessAudit\Domain\Repository\PermissionRepositoryInterface;
use App\AccessAudit\Domain\Repository\UtilisateurPermissionRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api')]
final class UserPermissionController extends AbstractController
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissionRepository,
        private readonly UtilisateurPermissionRepositoryInterface $utilisateurPermissionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/permissions', name: 'api_permissions_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function listPermissions(): JsonResponse
    {
        $permissions = $this->permissionRepository->findAllOrdered();

        if ([] === $permissions) {
            return $this->json([
                'data' => PermissionCatalog::all(),
                'role_permissions' => PermissionCatalog::rolePermissions(),
            ]);
        }

        $data = array_map(static fn ($permission) => [
            'id' => (string) $permission->getId(),
            'code' => $permission->getCode(),
            'libelle' => $permission->getLibelle(),
            'description' => $permission->getDescription(),
            'module' => $permission->getModule(),
            'is_enabled' => $permission->isEnabled(),
        ], $permissions);

        return $this->json([
            'data' => $data,
            'role_permissions' => PermissionCatalog::rolePermissions(),
        ]);
    }

    #[Route('/users/{id}/permissions', name: 'api_users_permissions_get', methods: ['GET'])]
    #[IsGranted('access.permissions.manage')]
    public function getPermissions(string $id): JsonResponse
    {
        $utilisateur = $this->entityManager->find(Utilisateur::class, Uuid::fromString($id));
        if (null === $utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $overrides = $this->utilisateurPermissionRepository->findByUtilisateurId($utilisateur->getId());

        return $this->json([
            'utilisateur_id' => (string) $utilisateur->getId(),
            'role' => $utilisateur->getRole()->value,
            'permissions' => array_map(static fn (UtilisateurPermission $up) => [
                'code' => $up->getPermission()->getCode(),
                'accorde' => $up->isAccorde(),
                'attribue_par_id' => (string) $up->getAttribueParId(),
                'date_attribution' => $up->getDateAttribution()->format(\DateTimeInterface::ATOM),
            ], $overrides),
        ]);
    }

    #[Route('/users/{id}/permissions', name: 'api_users_permissions_update', methods: ['PUT'])]
    #[IsGranted('access.permissions.manage')]
    public function updatePermissions(string $id, Request $request): JsonResponse
    {
        $utilisateur = $this->entityManager->find(Utilisateur::class, Uuid::fromString($id));
        if (null === $utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->toArray();
        if (!isset($payload['permissions']) || !is_array($payload['permissions'])) {
            return $this->json(['error' => 'permissions is required'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($payload['permissions'] as $item) {
            if (!is_array($item) || empty($item['code']) || !array_key_exists('accorde', $item)) {
                return $this->json(['error' => 'Each permission must have code and accorde'], Response::HTTP_BAD_REQUEST);
            }

            $permission = $this->permissionRepository->findByCode((string) $item['code']);
            if (null === $permission) {
                return $this->json(['error' => 'Permission inconnue : '.$item['code']], Response::HTTP_BAD_REQUEST);
            }

            $existing = $this->utilisateurPermissionRepository->findOneByUtilisateurAndPermission(
                $utilisateur->getId(),
                $permission->getId(),
            );

            if (null === $item['accorde']) {
                if (null !== $existing) {
                    $this->utilisateurPermissionRepository->remove($existing, false);
                }
                continue;
            }

            if (null === $existing) {
                $this->utilisateurPermissionRepository->save(new UtilisateurPermission(
                    $utilisateur->getId(),
                    $permission,
                    (bool) $item['accorde'],
                    $currentUser->getId(),
                ), false);
            } else {
                $existing->setAccorde((bool) $item['accorde']);
                $this->utilisateurPermissionRepository->save($existing, false);
            }
        }

        $this->entityManager->flush();

        $overrides = $this->utilisateurPermissionRepository->findByUtilisateurId($utilisateur->getId());

        return $this->json([
            'utilisateur_id' => (string) $utilisateur->getId(),
            'permissions' => array_map(static fn (UtilisateurPermission $up) => [
                'code' => $up->getPermission()->getCode(),
                'accorde' => $up->isAccorde(),
                'attribue_par_id' => (string) $up->getAttribueParId(),
                'date_attribution' => $up->getDateAttribution()->format(\DateTimeInterface::ATOM),
            ], $overrides),
        ]);
    }
}
