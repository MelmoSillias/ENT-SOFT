<?php

namespace App\Employee\Application\Command\CreateEmployee;

final readonly class CreateEmployeeCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $function,
        public ?string $address = null,
        public ?string $userId = null,
    ) {
    }
}
