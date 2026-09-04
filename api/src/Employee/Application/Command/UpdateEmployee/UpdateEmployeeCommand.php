<?php

namespace App\Employee\Application\Command\UpdateEmployee;

final readonly class UpdateEmployeeCommand
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $function = null,
        public ?string $address = null,
        public ?string $userId = null,
        public bool $hasAddress = false,
        public bool $hasUserId = false,
    ) {
    }
}
