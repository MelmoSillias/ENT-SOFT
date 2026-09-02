<?php

namespace App\IdentityAccess\Application\Dto;

use App\IdentityAccess\Domain\Entity\Utilisateur;

final readonly class UserResponseDto
{
    public function __construct(
        public string $id,
        public string $prenom,
        public string $nom,
        public string $telephone,
        public string $login,
        public string $role,
        public bool $isActive,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Utilisateur $user): self
    {
        return new self(
            id: (string) $user->getId(),
            prenom: $user->getPrenom(),
            nom: $user->getNom(),
            telephone: $user->getTelephone(),
            login: $user->getLogin(),
            role: $user->getRole()->value,
            isActive: $user->isActive(),
            isEnabled: $user->isEnabled(),
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'telephone' => $this->telephone,
            'login' => $this->login,
            'role' => $this->role,
            'isActive' => $this->isActive,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
