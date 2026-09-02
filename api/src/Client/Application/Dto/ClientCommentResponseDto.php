<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\ClientComment;

final readonly class ClientCommentResponseDto
{
    public function __construct(
        public string $id,
        public string $clientId,
        public string $content,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(ClientComment $comment): self
    {
        return new self(
            id: (string) $comment->getId(),
            clientId: (string) $comment->getClientId(),
            content: $comment->getContent(),
            createdAt: $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->clientId,
            'content' => $this->content,
            'createdAt' => $this->createdAt,
        ];
    }
}
