<?php

namespace App\Task\Application\Query\GetTask;

final readonly class GetTaskQuery
{
    public function __construct(public string $id) {}
}
