<?php

namespace App\IdentityAccess\Application\Query\ListUsers;

final readonly class ListUsersQuery
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
