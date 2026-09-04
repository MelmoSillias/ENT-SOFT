<?php

namespace App\Prestataire\Application\Command\CreatePrestataire;

use App\Prestataire\Application\Dto\PrestataireResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Entity\Prestataire;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;

final class CreatePrestataireHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(CreatePrestataireCommand $command): PrestataireResponseDto
    {
        $prestataire = new Prestataire(
            prenom: FieldValidator::requireNonEmpty($command->prenom, 'Prénom'),
            nom: FieldValidator::requireNonEmpty($command->nom, 'Nom'),
            email: FieldValidator::requireNonEmpty($command->email, 'Email'),
            phone: FieldValidator::requirePhone($command->phone),
            address: $command->address,
        );
        $this->prestataireRepository->save($prestataire);

        return $this->assembler->toPrestataireDto($prestataire);
    }
}
