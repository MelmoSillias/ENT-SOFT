<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\Client;

final readonly class ClientDetailResponseDto
{
    /**
     * @param list<array<string, mixed>> $comments
     * @param list<array<string, mixed>> $contacts
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $description,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
        public int $projectCount,
        public int $invoiceCount,
        public array $comments,
        public array $contacts,
    ) {
    }

    public static function fromEntity(
        Client $client,
        int $projectCount,
        int $invoiceCount,
        array $comments,
        array $contacts = [],
    ): self {
        return new self(
            id: (string) $client->getId(),
            code: $client->getCode(),
            title: $client->getTitle(),
            description: $client->getDescription(),
            isEnabled: $client->isEnabled(),
            createdAt: $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $client->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            projectCount: $projectCount,
            invoiceCount: $invoiceCount,
            comments: $comments,
            contacts: $contacts,
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
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'projectCount' => $this->projectCount,
            'invoiceCount' => $this->invoiceCount,
            'comments' => $this->comments,
            'contacts' => $this->contacts,
        ];
    }
}
