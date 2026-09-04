<?php

namespace App\Prestataire\Application\Command\DeletePrestataire;

use App\Prestataire\Application\Dto\PrestataireResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Exception\PrestataireNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeletePrestataireHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(DeletePrestataireCommand $command): PrestataireResponseDto
    {
        $prestataire = $this->prestataireRepository->findById(Uuid::fromString($command->id));
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestataireNotFoundException::withId($command->id);
        }

        $prestataire->disable();
        $this->prestataireRepository->save($prestataire);

        return $this->assembler->toPrestataireDto($prestataire);
    }
}
