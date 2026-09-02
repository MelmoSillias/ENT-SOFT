<?php

namespace App\IdentityAccess\Application\Command\CreateUser;

use App\IdentityAccess\Application\Dto\UserResponseDto;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(CreateUserCommand $command): UserResponseDto
    {
        $prenom = FieldValidator::requireNonEmpty($command->prenom, 'Prénom');
        $nom = FieldValidator::requireNonEmpty($command->nom, 'Nom');
        $telephone = FieldValidator::requirePhone($command->telephone);
        $login = FieldValidator::requireNonEmpty($command->login, 'Login');
        $password = FieldValidator::requireMinLength($command->password, 6, 'Mot de passe');

        if (null !== $this->utilisateurRepository->findByLogin($login)) {
            throw new \InvalidArgumentException('Ce login est déjà utilisé.');
        }

        $user = new Utilisateur(
            prenom: $prenom,
            nom: $nom,
            telephone: $telephone,
            login: $login,
            passwordHash: '',
            role: $command->role,
        );
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

        $this->utilisateurRepository->save($user);

        return UserResponseDto::fromEntity($user);
    }
}
