<?php

namespace App\Client\Application\Query\GetClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(GetClientQuery $query): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($query->id));
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($query->id);
        }

        return ClientResponseDto::fromEntity($client);
    }
}
