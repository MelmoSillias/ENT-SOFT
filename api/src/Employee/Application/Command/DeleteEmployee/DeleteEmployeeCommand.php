<?php

namespace App\Employee\Application\Command\DeleteEmployee;

final readonly class DeleteEmployeeCommand
{
    public function __construct(public string $id) {}
}
