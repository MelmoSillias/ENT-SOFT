<?php

namespace App\IdentityAccess\Application\Query\ListUsers;

use App\IdentityAccess\Application\Dto\UserResponseDto;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;

final class ListUsersHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListUsersQuery $query): array
    {
        return array_map(
            static fn ($user) => UserResponseDto::fromEntity($user)->toArray(),
            $this->utilisateurRepository->findAll(),
        );
    }
}
