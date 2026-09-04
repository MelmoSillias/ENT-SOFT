<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\Client;

final readonly class ClientResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $description,
        public ?string $address,
        public ?string $postalBox,
        public ?string $city,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Client $client): self
    {
        return new self(
            id: (string) $client->getId(),
            code: $client->getCode(),
            title: $client->getTitle(),
            description: $client->getDescription(),
            address: $client->getAddress(),
            postalBox: $client->getPostalBox(),
            city: $client->getCity(),
            isEnabled: $client->isEnabled(),
            createdAt: $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $client->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'postalBox' => $this->postalBox,
            'city' => $this->city,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
