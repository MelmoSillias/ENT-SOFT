<?php

namespace App\IdentityAccess\Application\Command\UpdateUser;

use App\IdentityAccess\Domain\Enum\Role;

final readonly class UpdateUserCommand
{
    public function __construct(
        public string $id,
        public ?string $prenom = null,
        public ?string $nom = null,
        public ?string $telephone = null,
        public ?string $login = null,
        public ?string $password = null,
        public ?Role $role = null,
        public ?bool $isActive = null,
    ) {
    }
}
