<?php

namespace App\Client\Application\Command\CreateClientComment;

use App\Client\Application\Dto\ClientCommentResponseDto;
use App\Client\Domain\Entity\ClientComment;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientCommentRepositoryInterface;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateClientCommentHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ClientCommentRepositoryInterface $commentRepository,
    ) {
    }

    public function handle(CreateClientCommentCommand $command): ClientCommentResponseDto
    {
        $clientId = Uuid::fromString($command->clientId);
        $client = $this->clientRepository->findById($clientId);
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->clientId);
        }

        $content = FieldValidator::requireNonEmpty($command->content, 'Contenu');
        $comment = new ClientComment($clientId, $content);
        $this->commentRepository->save($comment);

        return ClientCommentResponseDto::fromEntity($comment);
    }
}
