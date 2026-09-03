<?php

namespace App\Client\Application\Command\CreateClientContact;

use App\Client\Application\Dto\ClientContactResponseDto;
use App\Client\Domain\Entity\ClientContact;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientContactRepositoryInterface;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateClientContactHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ClientContactRepositoryInterface $contactRepository,
    ) {
    }

    public function handle(CreateClientContactCommand $command): ClientContactResponseDto
    {
        $clientId = Uuid::fromString($command->clientId);
        $client = $this->clientRepository->findById($clientId);
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->clientId);
        }

        $name = trim($command->name);
        $phone = trim($command->phone);
        if ($name === '' || $phone === '') {
            throw new \InvalidArgumentException('Le nom et le téléphone du contact sont obligatoires.');
        }

        $contact = new ClientContact($clientId, $name, $phone);
        $this->contactRepository->save($contact);

        return ClientContactResponseDto::fromEntity($contact);
    }
}
