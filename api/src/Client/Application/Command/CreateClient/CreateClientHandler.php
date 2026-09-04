<?php

namespace App\Client\Application\Command\CreateClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;

final class CreateClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateClientCommand $command): ClientResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::CLIENT);
        $client = new Client(
            $code,
            $title,
            $command->description,
            $command->address,
            $command->postalBox,
            $command->city,
        );
        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}
