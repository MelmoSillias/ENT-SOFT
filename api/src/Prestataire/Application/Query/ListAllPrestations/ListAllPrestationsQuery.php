<?php

namespace App\Prestataire\Application\Query\ListAllPrestations;

final readonly class ListAllPrestationsQuery
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
