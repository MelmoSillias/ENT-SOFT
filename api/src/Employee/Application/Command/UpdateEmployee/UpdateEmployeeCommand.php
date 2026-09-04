<?php

namespace App\Employee\Application\Command\UpdateEmployee;

final readonly class UpdateEmployeeCommand
{
    public function __construct(
        public string $id,
        public ?string $prenom = null,
        public ?string $nom = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $roleCode = null,
        public ?string $address = null,
        public bool $hasAddress = false,
    ) {
    }
}
