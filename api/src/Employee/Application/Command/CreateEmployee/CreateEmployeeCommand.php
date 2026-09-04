<?php

namespace App\Employee\Application\Command\CreateEmployee;

final readonly class CreateEmployeeCommand
{
    public function __construct(
        public string $prenom,
        public string $nom,
        public string $email,
        public string $phone,
        public string $roleCode,
        public ?string $address = null,
    ) {
    }
}
