<?php

namespace App\IdentityAccess\Application\Query\GetUser;

final readonly class GetUserQuery
{
    public function __construct(
        public string $id,
    ) {
    }
}
