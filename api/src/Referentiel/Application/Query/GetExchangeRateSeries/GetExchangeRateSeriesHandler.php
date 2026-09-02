<?php

namespace App\Referentiel\Application\Query\GetExchangeRateSeries;

use App\Referentiel\Infrastructure\External\FrankfurterClient;

final class GetExchangeRateSeriesHandler
{
    public function __construct(
        private readonly FrankfurterClient $frankfurterClient,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(
        string $base = 'EUR',
        string $quote = 'XAF',
        ?string $from = null,
        ?string $to = null,
    ): array {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        $toDate = null !== $to && '' !== $to
            ? new \DateTimeImmutable($to)
            : new \DateTimeImmutable('today');

        $fromDate = null !== $from && '' !== $from
            ? new \DateTimeImmutable($from)
            : $toDate->sub(new \DateInterval('P30D'));

        if ($fromDate > $toDate) {
            throw new \InvalidArgumentException('La date de début doit être antérieure à la date de fin.');
        }

        $points = $this->frankfurterClient->getTimeSeries(
            $base,
            $quote,
            $fromDate->format('Y-m-d'),
            $toDate->format('Y-m-d'),
        );

        return [
            'base' => $base,
            'quote' => $quote,
            'from' => $fromDate->format('Y-m-d'),
            'to' => $toDate->format('Y-m-d'),
            'points' => $points,
        ];
    }
}
