<?php

namespace App\IdentityAccess\Application\Service;

use App\IdentityAccess\Domain\Entity\Utilisateur;

final class AccessTokenService
{
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function create(Utilisateur $user): string
    {
        $payload = json_encode([
            'sub' => $user->getLogin(),
            'exp' => time() + self::TTL_SECONDS,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, $this->appSecret);

        return base64_encode($payload) . '.' . $signature;
    }

    public function validate(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $payload = base64_decode($encodedPayload, true);
        if (false === $payload) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->appSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        /** @var array{sub?: string, exp?: int} $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['sub'], $data['exp']) || $data['exp'] < time()) {
            return null;
        }

        return $data['sub'];
    }
}
