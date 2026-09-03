<?php

namespace App\Client\Application\Query\GetClientDetail;

use App\Client\Application\Dto\ClientCommentResponseDto;
use App\Client\Application\Dto\ClientContactResponseDto;
use App\Client\Application\Dto\ClientDetailResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientCommentRepositoryInterface;
use App\Client\Domain\Repository\ClientContactRepositoryInterface;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetClientDetailHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ClientCommentRepositoryInterface $commentRepository,
        private readonly ClientContactRepositoryInterface $contactRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(GetClientDetailQuery $query): ClientDetailResponseDto
    {
        $clientId = Uuid::fromString($query->id);
        $client = $this->clientRepository->findById($clientId);
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($query->id);
        }

        $comments = array_map(
            static fn ($c) => ClientCommentResponseDto::fromEntity($c)->toArray(),
            $this->commentRepository->findByClientId($clientId),
        );

        $contacts = array_map(
            static fn ($c) => ClientContactResponseDto::fromEntity($c)->toArray(),
            $this->contactRepository->findByClientId($clientId),
        );

        return ClientDetailResponseDto::fromEntity(
            $client,
            $this->projectRepository->countByClientId($clientId),
            $this->invoiceRepository->countByClientId($clientId),
            $comments,
            $contacts,
        );
    }
}
