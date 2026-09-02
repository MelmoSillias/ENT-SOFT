<?php

namespace App\Client\Application\Query\ListDeletedClients;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Repository\ClientRepositoryInterface;

final class ListDeletedClientsHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(
            static fn ($client) => ClientResponseDto::fromEntity($client)->toArray(),
            $this->clientRepository->findAllDisabled(),
        );
    }
}
