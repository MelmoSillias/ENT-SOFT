<?php

namespace App\Document\Application\Query\ListDocumentsByOwner;

use App\Document\Application\Dto\DocumentResponseDto;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListDocumentsByOwnerHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListDocumentsByOwnerQuery $query): array
    {
        $documents = $this->documentRepository->findByOwner(
            DocumentOwnerType::from($query->ownerType),
            Uuid::fromString($query->ownerId),
        );

        return array_map(
            static fn ($d) => DocumentResponseDto::fromEntity($d)->toArray(),
            $documents,
        );
    }
}
