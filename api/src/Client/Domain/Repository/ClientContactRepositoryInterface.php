<?php

namespace App\Client\Domain\Repository;

use App\Client\Domain\Entity\ClientContact;
use Symfony\Component\Uid\Uuid;

interface ClientContactRepositoryInterface
{
    public function save(ClientContact $contact): void;

    public function remove(ClientContact $contact): void;

    public function findById(Uuid $id): ?ClientContact;

    /** @return list<ClientContact> */
    public function findByClientId(Uuid $clientId): array;
}
