<?php

namespace App\Employee\Application\Command\CheckInEmployeePosition;

final readonly class CheckInEmployeePositionCommand
{
    public function __construct(
        public string $userId,
        public float $latitude,
        public float $longitude,
        public ?float $accuracy = null,
    ) {
    }
}
