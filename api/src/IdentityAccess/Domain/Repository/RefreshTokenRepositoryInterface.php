<?php

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\Entity\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function save(RefreshToken $token): void;

    public function remove(RefreshToken $token): void;

    public function findByHash(string $hash): ?RefreshToken;
}
