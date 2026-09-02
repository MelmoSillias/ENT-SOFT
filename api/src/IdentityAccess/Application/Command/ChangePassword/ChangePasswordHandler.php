<?php

namespace App\IdentityAccess\Application\Command\ChangePassword;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChangePasswordHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(Utilisateur $user, ChangePasswordCommand $command): void
    {
        $currentPassword = FieldValidator::requireNonEmpty($command->currentPassword, 'Mot de passe actuel');
        $newPassword = FieldValidator::requireMinLength($command->newPassword, 6, 'Nouveau mot de passe');

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new \InvalidArgumentException('Le mot de passe actuel est incorrect.');
        }

        if ($currentPassword === $newPassword) {
            throw new \InvalidArgumentException('Le nouveau mot de passe doit être différent de l\'ancien.');
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $newPassword));
        $this->utilisateurRepository->save($user);
    }
}
