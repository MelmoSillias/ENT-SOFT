<?php

namespace App\Client\Application\Command\RestoreClient;

final readonly class RestoreClientCommand
{
    public function __construct(public string $id) {}
}
