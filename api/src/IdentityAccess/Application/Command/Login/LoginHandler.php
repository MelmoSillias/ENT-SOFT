<?php

namespace App\IdentityAccess\Application\Command\Login;

use App\IdentityAccess\Application\Service\RefreshTokenService;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginHandler
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    /** @return array{access_token: string, refresh_token: string, token_type: string}|null */
    public function handle(string $login, string $password): ?array
    {
        $user = $this->utilisateurRepository->findByLogin($login);
        if (null === $user || !$user->isEnabled() || !$user->isActive()) {
            return null;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        return $this->refreshTokenService->createTokenPair($user);
    }
}
