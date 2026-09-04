<?php

namespace App\Prestataire\Application\Query\ListPrestataires;

final readonly class ListPrestatairesQuery
{
    public function __construct(public ?string $search = null) {}
}
