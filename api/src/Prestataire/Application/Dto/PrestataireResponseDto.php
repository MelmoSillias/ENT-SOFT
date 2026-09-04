<?php

namespace App\Prestataire\Application\Dto;

use App\Prestataire\Domain\Entity\Prestataire;

final readonly class PrestataireResponseDto
{
    public function __construct(
        public string $id,
        public string $prenom,
        public string $nom,
        public string $name,
        public string $email,
        public string $phone,
        public ?string $address,
        public bool $isEnabled,
        public int $openPrestationsCount,
        public float $unpaidCompletedReliquat,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(
        Prestataire $prestataire,
        int $openPrestationsCount = 0,
        float $unpaidCompletedReliquat = 0.0,
    ): self {
        return new self(
            id: (string) $prestataire->getId(),
            prenom: $prestataire->getPrenom(),
            nom: $prestataire->getNom(),
            name: $prestataire->getFullName(),
            email: $prestataire->getEmail(),
            phone: $prestataire->getPhone(),
            address: $prestataire->getAddress(),
            isEnabled: $prestataire->isEnabled(),
            openPrestationsCount: $openPrestationsCount,
            unpaidCompletedReliquat: $unpaidCompletedReliquat,
            createdAt: $prestataire->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $prestataire->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'isEnabled' => $this->isEnabled,
            'openPrestationsCount' => $this->openPrestationsCount,
            'unpaidCompletedReliquat' => $this->unpaidCompletedReliquat,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
