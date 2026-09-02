<?php

namespace App\Client\Application\Command\DeleteClient;

final readonly class DeleteClientCommand
{
    public function __construct(public string $id) {}
}
