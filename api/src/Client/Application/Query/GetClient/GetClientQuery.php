<?php

namespace App\Client\Application\Query\GetClient;

final readonly class GetClientQuery
{
    public function __construct(public string $id) {}
}
