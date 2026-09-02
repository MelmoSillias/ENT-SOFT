<?php

namespace App\IdentityAccess\Application\Command\UpdateUser;

use App\IdentityAccess\Application\Dto\UserResponseDto;
use App\IdentityAccess\Domain\Exception\UserNotFoundException;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateUserHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(UpdateUserCommand $command): UserResponseDto
    {
        $user = $this->utilisateurRepository->findById(Uuid::fromString($command->id));
        if (null === $user) {
            throw UserNotFoundException::withId($command->id);
        }

        if (null !== $command->prenom) {
            $user->setPrenom(FieldValidator::requireNonEmpty($command->prenom, 'Prénom'));
        }
        if (null !== $command->nom) {
            $user->setNom(FieldValidator::requireNonEmpty($command->nom, 'Nom'));
        }
        if (null !== $command->telephone) {
            $user->setTelephone(FieldValidator::requirePhone($command->telephone));
        }
        if (null !== $command->login) {
            $login = FieldValidator::requireNonEmpty($command->login, 'Login');
            $existing = $this->utilisateurRepository->findByLogin($login);
            if (null !== $existing && !$existing->getId()->equals($user->getId())) {
                throw new \InvalidArgumentException('Ce login est déjà utilisé.');
            }
            $user->setLogin($login);
        }
        if (null !== $command->password) {
            $password = FieldValidator::requireMinLength($command->password, 6, 'Mot de passe');
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        }
        if (null !== $command->role) {
            $user->setRole($command->role);
        }
        if (null !== $command->isActive) {
            $user->setIsActive($command->isActive);
        }

        $this->utilisateurRepository->save($user);

        return UserResponseDto::fromEntity($user);
    }
}
