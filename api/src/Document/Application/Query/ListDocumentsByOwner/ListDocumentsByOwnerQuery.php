<?php

namespace App\Document\Application\Query\ListDocumentsByOwner;

final readonly class ListDocumentsByOwnerQuery
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
    ) {
    }
}
