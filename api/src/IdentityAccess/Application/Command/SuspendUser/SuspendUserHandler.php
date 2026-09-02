<?php

namespace App\IdentityAccess\Application\Command\SuspendUser;

use App\IdentityAccess\Application\Dto\UserResponseDto;
use App\IdentityAccess\Domain\Exception\UserNotFoundException;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class SuspendUserHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
    ) {
    }

    public function handle(SuspendUserCommand $command): UserResponseDto
    {
        $user = $this->utilisateurRepository->findById(Uuid::fromString($command->id));
        if (null === $user) {
            throw UserNotFoundException::withId($command->id);
        }

        $user->suspend();
        $this->utilisateurRepository->save($user);

        return UserResponseDto::fromEntity($user);
    }
}
