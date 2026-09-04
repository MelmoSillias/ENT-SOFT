<?php

namespace App\Prestataire\Application\Query\ListPrestationsByPrestataire;

final readonly class ListPrestationsByPrestataireQuery
{
    public function __construct(public string $prestataireId) {}
}
