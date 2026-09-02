<?php

namespace App\Document\Application\Dto;

use App\Document\Domain\Entity\Document;

final readonly class DocumentResponseDto
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $fileName,
        public string $filePath,
        public string $ownerType,
        public string $ownerId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Document $document): self
    {
        return new self(
            id: (string) $document->getId(),
            title: $document->getTitle(),
            description: $document->getDescription(),
            fileName: $document->getFileName(),
            filePath: $document->getFilePath(),
            ownerType: $document->getOwnerType()->value,
            ownerId: (string) $document->getOwnerId(),
            isEnabled: $document->isEnabled(),
            createdAt: $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $document->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'fileName' => $this->fileName,
            'filePath' => $this->filePath,
            'ownerType' => $this->ownerType,
            'ownerId' => $this->ownerId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
