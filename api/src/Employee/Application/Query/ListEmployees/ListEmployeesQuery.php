<?php

namespace App\Employee\Application\Query\ListEmployees;

final readonly class ListEmployeesQuery
{
    public function __construct(public ?string $search = null) {}
}
