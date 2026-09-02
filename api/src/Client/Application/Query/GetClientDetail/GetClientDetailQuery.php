<?php

namespace App\Client\Application\Query\GetClientDetail;

final readonly class GetClientDetailQuery
{
    public function __construct(public string $id) {}
}
