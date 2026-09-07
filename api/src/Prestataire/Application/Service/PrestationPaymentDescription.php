<?php

namespace App\Prestataire\Application\Service;

final class PrestationPaymentDescription
{
    public static function build(string $prestataireFullName, int $prestationCount): string
    {
        $count = max(0, $prestationCount);
        $label = $count <= 1 ? '1 prestation' : $count.' prestations';

        return sprintf('Paiement prestation — %s (%s)', trim($prestataireFullName), $label);
    }
}
