<?php

namespace App\Referentiel\Application\Query\CompareExchangeRate;

use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Referentiel\Infrastructure\External\FrankfurterClient;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CompareExchangeRateHandler
{
    public function __construct(
        private readonly FrankfurterClient $frankfurterClient,
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(string $base = 'EUR', string $quote = 'XAF'): array
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        $internal = $this->resolveInternalRate($base, $quote);

        try {
            $external = $this->frankfurterClient->getRate($base, $quote);
            $frankfurterRate = $external['rate'];
            $date = $external['date'];
        } catch (HttpException) {
            return [
                'base' => $base,
                'quote' => $quote,
                'frankfurter' => null,
                'internal' => $internal,
                'delta' => null,
                'deltaPercent' => null,
                'date' => null,
                'frankfurterUnavailable' => true,
            ];
        }

        $delta = null;
        $deltaPercent = null;

        if (null !== $internal) {
            $delta = round($frankfurterRate - (float) $internal, 6);
            $internalFloat = (float) $internal;
            if (0.0 !== $internalFloat) {
                $deltaPercent = round(($delta / $internalFloat) * 100, 4);
            }
        }

        return [
            'base' => $base,
            'quote' => $quote,
            'frankfurter' => $frankfurterRate,
            'internal' => $internal,
            'delta' => $delta,
            'deltaPercent' => $deltaPercent,
            'date' => $date,
            'frankfurterUnavailable' => false,
        ];
    }

    private function resolveInternalRate(string $base, string $quote): ?string
    {
        $settingKey = sprintf('TAUX_%s_%s', $base, $quote);
        $setting = $this->settingRepository->findByCle($settingKey);

        return $setting?->getValeur();
    }
}
