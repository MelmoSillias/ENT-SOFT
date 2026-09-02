<?php

namespace App\Client\Application\Query\ListClients;

final readonly class ListClientsQuery
{
    public function __construct(public ?string $search = null) {}
}
