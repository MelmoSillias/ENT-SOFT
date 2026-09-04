<?php

namespace App\Prestataire\Application\Query\ListPrestataires;

use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;

final class ListPrestatairesHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListPrestatairesQuery $query): array
    {
        return array_map(
            fn ($p) => $this->assembler->toPrestataireDto($p)->toArray(),
            $this->prestataireRepository->findAllEnabled($query->search),
        );
    }
}
