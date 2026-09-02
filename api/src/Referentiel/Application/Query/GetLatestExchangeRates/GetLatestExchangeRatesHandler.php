<?php

namespace App\Referentiel\Application\Query\GetLatestExchangeRates;

use App\Referentiel\Domain\MajorCurrencies;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use App\Referentiel\Infrastructure\External\FrankfurterClient;

final class GetLatestExchangeRatesHandler
{
    public function __construct(
        private readonly FrankfurterClient $frankfurterClient,
        private readonly DeviseRepositoryInterface $deviseRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(string $base = 'EUR', ?string $quotesParam = null): array
    {
        $base = strtoupper($base);
        $quotes = $this->resolveQuotes($base, $quotesParam);

        $payload = $this->frankfurterClient->getLatestRates($base, $quotes);

        $rates = array_map(
            static fn (array $row) => [
                'quote' => $row['quote'],
                'rate' => $row['rate'],
                'source' => 'frankfurter',
            ],
            $payload['rates'],
        );

        usort($rates, static fn (array $a, array $b) => strcmp($a['quote'], $b['quote']));

        return [
            'date' => $payload['date'],
            'base' => $payload['base'],
            'rates' => $rates,
        ];
    }

    /** @return list<string> */
    private function resolveQuotes(string $base, ?string $quotesParam): array
    {
        if (null !== $quotesParam && '' !== trim($quotesParam)) {
            return array_values(array_unique(array_map(
                'strtoupper',
                array_filter(array_map('trim', explode(',', $quotesParam))),
            )));
        }

        $quotes = MajorCurrencies::forBase($base);

        foreach ($this->deviseRepository->findAll() as $devise) {
            if ($devise->isActif() && $devise->getCode() !== $base) {
                $quotes[] = $devise->getCode();
            }
        }

        $quotes = array_values(array_unique($quotes));

        return array_slice($quotes, 0, MajorCurrencies::DEFAULT_COUNT);
    }
}
