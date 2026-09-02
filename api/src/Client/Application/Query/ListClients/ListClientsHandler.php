<?php

namespace App\Client\Application\Query\ListClients;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Repository\ClientRepositoryInterface;

final class ListClientsHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListClientsQuery $query): array
    {
        return array_map(
            static fn ($client) => ClientResponseDto::fromEntity($client)->toArray(),
            $this->clientRepository->findAllEnabled($query->search),
        );
    }
}
