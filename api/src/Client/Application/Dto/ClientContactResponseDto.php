<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\ClientContact;

final readonly class ClientContactResponseDto
{
    public function __construct(
        public string $id,
        public string $clientId,
        public string $name,
        public string $phone,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(ClientContact $contact): self
    {
        return new self(
            id: (string) $contact->getId(),
            clientId: (string) $contact->getClientId(),
            name: $contact->getName(),
            phone: $contact->getPhone(),
            createdAt: $contact->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->clientId,
            'name' => $this->name,
            'phone' => $this->phone,
            'createdAt' => $this->createdAt,
        ];
    }
}
