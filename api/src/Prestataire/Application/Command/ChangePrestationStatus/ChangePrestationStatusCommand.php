<?php

namespace App\Prestataire\Application\Command\ChangePrestationStatus;

final readonly class ChangePrestationStatusCommand
{
    public function __construct(
        public string $id,
        public string $workStatus,
    ) {
    }
}
