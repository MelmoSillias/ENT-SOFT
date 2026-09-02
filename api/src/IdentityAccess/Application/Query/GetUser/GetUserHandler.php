<?php

namespace App\IdentityAccess\Application\Query\GetUser;

use App\IdentityAccess\Application\Dto\UserResponseDto;
use App\IdentityAccess\Domain\Exception\UserNotFoundException;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetUserHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
    ) {
    }

    public function handle(GetUserQuery $query): UserResponseDto
    {
        $user = $this->utilisateurRepository->findById(Uuid::fromString($query->id));
        if (null === $user) {
            throw UserNotFoundException::withId($query->id);
        }

        return UserResponseDto::fromEntity($user);
    }
}
