<?php

namespace App\IdentityAccess\Application\Command\SuspendUser;

final readonly class SuspendUserCommand
{
    public function __construct(
        public string $id,
    ) {
    }
}
