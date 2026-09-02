<?php

namespace App\IdentityAccess\Application\Service;

use App\IdentityAccess\Domain\Entity\RefreshToken;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\RefreshTokenRepositoryInterface;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;

final class RefreshTokenService
{
    private const REFRESH_TTL_DAYS = 7;

    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
    ) {
    }

    /** @return array{access_token: string, refresh_token: string, token_type: string} */
    public function createTokenPair(Utilisateur $user): array
    {
        $access = $this->accessTokenService->create($user);
        $raw = bin2hex(random_bytes(32));
        $this->refreshTokenRepository->save(new RefreshToken(
            $user->getId(),
            hash('sha256', $raw),
            new \DateTimeImmutable('+' . self::REFRESH_TTL_DAYS . ' days'),
        ));

        return [
            'access_token' => $access,
            'refresh_token' => $raw,
            'token_type' => 'Bearer',
        ];
    }

    /** @return array{access_token: string, refresh_token: string, token_type: string}|null */
    public function refresh(string $rawToken): ?array
    {
        $stored = $this->refreshTokenRepository->findByHash(hash('sha256', $rawToken));
        if (null === $stored || $stored->isExpired()) {
            return null;
        }

        $user = $this->utilisateurRepository->findById($stored->getUtilisateurId());
        if (null === $user || !$user->isEnabled() || !$user->isActive()) {
            return null;
        }

        $this->refreshTokenRepository->remove($stored);

        return $this->createTokenPair($user);
    }
}
