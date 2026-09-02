<?php

namespace App\Client\Application\Command\CreateClientComment;

final readonly class CreateClientCommentCommand
{
    public function __construct(
        public string $clientId,
        public string $content,
    ) {
    }
}
