<?php

namespace App\Client\Application\Command\UpdateClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(UpdateClientCommand $command): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($command->id));
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $client->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->description !== null) {
            $client->setDescription($command->description);
        }

        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}
