<?php

namespace App\Prestataire\Application\Command\PayPrestations;

final readonly class PayPrestationsCommand
{
    /**
     * @param list<array{prestationId: string, amount: float}> $allocations
     */
    public function __construct(
        public string $prestataireId,
        public array $allocations,
        public ?string $date = null,
    ) {
    }
}
