<?php

namespace App\Site\Application\Dto;

use App\Site\Domain\Entity\Site;

final readonly class SiteResponseDto
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

    public static function fromEntity(Site $site): self
    {
        return new self(
            id: (string) $site->getId(),
            code: $site->getCode(),
            title: $site->getTitle(),
            description: $site->getDescription(),
            clientId: $site->getClientId()?->toRfc4122(),
            isEnabled: $site->isEnabled(),
            createdAt: $site->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $site->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
