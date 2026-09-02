<?php

namespace App\Referentiel\Application\Service;

use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\Referentiel\Domain\Repository\ReferenceSequenceRepositoryInterface;

final class CodeGeneratorService
{
    public function __construct(
        private readonly ReferenceSequenceRepositoryInterface $sequenceRepository,
        private readonly ReferenceSettingsReader $settingsReader,
    ) {
    }

    public function generate(ReferenceSequenceType $type): string
    {
        $sequence = $this->sequenceRepository->getAndIncrement($type);

        return $this->settingsReader->formater($type, $sequence);
    }
}
