<?php

namespace App\Prestataire\Application\Command\UpdatePrestataire;

use App\Prestataire\Application\Dto\PrestataireResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Exception\PrestataireNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdatePrestataireHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(UpdatePrestataireCommand $command): PrestataireResponseDto
    {
        $prestataire = $this->prestataireRepository->findById(Uuid::fromString($command->id));
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestataireNotFoundException::withId($command->id);
        }

        if ($command->prenom !== null) {
            $prestataire->setPrenom(FieldValidator::requireNonEmpty($command->prenom, 'Prénom'));
        }
        if ($command->nom !== null) {
            $prestataire->setNom(FieldValidator::requireNonEmpty($command->nom, 'Nom'));
        }
        if ($command->email !== null) {
            $prestataire->setEmail(FieldValidator::requireNonEmpty($command->email, 'Email'));
        }
        if ($command->phone !== null) {
            $prestataire->setPhone(FieldValidator::requirePhone($command->phone));
        }
        if ($command->hasAddress) {
            $prestataire->setAddress($command->address);
        }

        $this->prestataireRepository->save($prestataire);

        return $this->assembler->toPrestataireDto($prestataire);
    }
}
