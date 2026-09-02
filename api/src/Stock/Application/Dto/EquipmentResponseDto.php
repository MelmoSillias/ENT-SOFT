<?php

namespace App\Stock\Application\Dto;

use App\Stock\Domain\Entity\Equipment;

final readonly class EquipmentResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $description,
        public ?string $clientId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Equipment $equipment): self
    {
        return new self(
            id: (string) $equipment->getId(),
            code: $equipment->getCode(),
            title: $equipment->getTitle(),
            description: $equipment->getDescription(),
            clientId: $equipment->getClientId()?->toRfc4122(),
            isEnabled: $equipment->isEnabled(),
            createdAt: $equipment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $equipment->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
            'clientId' => $this->clientId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
