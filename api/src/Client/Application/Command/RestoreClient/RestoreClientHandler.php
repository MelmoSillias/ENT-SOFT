<?php

namespace App\Client\Application\Command\RestoreClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class RestoreClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(RestoreClientCommand $command): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($command->id));
        if (null === $client) {
            throw ClientNotFoundException::withId($command->id);
        }

        $client->enable();
        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}
