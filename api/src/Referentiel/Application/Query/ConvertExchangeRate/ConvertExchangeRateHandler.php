<?php

namespace App\Referentiel\Application\Query\ConvertExchangeRate;

use App\Referentiel\Infrastructure\External\FrankfurterClient;

final class ConvertExchangeRateHandler
{
    public function __construct(
        private readonly FrankfurterClient $frankfurterClient,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(string $from, string $to, string $amount): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $amountFloat = (float) $amount;

        if ($amountFloat < 0) {
            throw new \InvalidArgumentException('Le montant doit être positif ou nul.');
        }

        $rateData = $this->frankfurterClient->getRate($from, $to);
        $result = round($amountFloat * $rateData['rate'], 6);

        return [
            'from' => $from,
            'to' => $to,
            'amount' => $amountFloat,
            'rate' => $rateData['rate'],
            'result' => $result,
            'date' => $rateData['date'],
        ];
    }
}
