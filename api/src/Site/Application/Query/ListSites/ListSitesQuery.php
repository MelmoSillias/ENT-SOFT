<?php

namespace App\Site\Application\Query\ListSites;

final readonly class ListSitesQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
