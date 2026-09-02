<?php

namespace App\Employee\Application\Query\GetEmployee;

final readonly class GetEmployeeQuery
{
    public function __construct(public string $id) {}
}
