<?php

namespace App\Document\Domain\Repository;

use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use Symfony\Component\Uid\Uuid;

interface DocumentRepositoryInterface
{
    public function save(Document $document): void;

    public function findById(Uuid $id): ?Document;

    /** @return list<Document> */
    public function findByOwner(DocumentOwnerType $ownerType, Uuid $ownerId): array;
}
