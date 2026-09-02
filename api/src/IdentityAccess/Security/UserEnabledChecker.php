<?php

namespace App\IdentityAccess\Security;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserEnabledChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if (!$user->isEnabled() || !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Compte suspendu ou désactivé.');
        }
    }
}
