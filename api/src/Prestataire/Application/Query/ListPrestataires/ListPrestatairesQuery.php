<?php

namespace App\Prestataire\Application\Query\ListPrestataires;

final readonly class ListPrestatairesQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
