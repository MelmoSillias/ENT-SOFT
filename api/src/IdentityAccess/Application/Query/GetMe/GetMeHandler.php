<?php

namespace App\IdentityAccess\Application\Query\GetMe;

use App\AccessAudit\Application\Service\PermissionResolverService;
use App\IdentityAccess\Domain\Entity\Utilisateur;

final class GetMeHandler
{
    public function __construct(
        private readonly PermissionResolverService $permissionResolver,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(Utilisateur $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
            'login' => $user->getLogin(),
            'telephone' => $user->getTelephone(),
            'role' => $user->getRole()->value,
            'isActive' => $user->isActive(),
            'permissions' => $this->permissionResolver->resolvePermissions($user),
        ];
    }
}
