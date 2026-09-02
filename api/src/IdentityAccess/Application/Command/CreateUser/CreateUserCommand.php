<?php

namespace App\IdentityAccess\Application\Command\CreateUser;

use App\IdentityAccess\Domain\Enum\Role;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $prenom,
        public string $nom,
        public string $telephone,
        public string $login,
        public string $password,
        public Role $role,
    ) {
    }
}
