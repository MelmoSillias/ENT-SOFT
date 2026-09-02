<?php

namespace App\Client\Domain\Repository;

use App\Client\Domain\Entity\Client;
use Symfony\Component\Uid\Uuid;

interface ClientRepositoryInterface
{
    public function save(Client $client): void;

    public function findById(Uuid $id): ?Client;

    /** @return list<Client> */
    public function findAllEnabled(?string $search = null): array;

    /** @return list<Client> */
    public function findAllDisabled(): array;

    public function countEnabled(): int;
}
