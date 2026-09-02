<?php

namespace App\IdentityAccess\Application\Command\ChangePassword;

final class ChangePasswordCommand
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword,
    ) {
    }
}
