<?php

namespace App\Client\Domain\Repository;

use App\Client\Domain\Entity\ClientComment;
use Symfony\Component\Uid\Uuid;

interface ClientCommentRepositoryInterface
{
    public function save(ClientComment $comment): void;

    /** @return list<ClientComment> */
    public function findByClientId(Uuid $clientId): array;
}
