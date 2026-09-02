<?php

namespace App\Client\Application\Command\DeleteClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(DeleteClientCommand $command): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($command->id));
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->id);
        }

        $client->disable();
        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}
