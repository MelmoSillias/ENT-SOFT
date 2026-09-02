<?php

namespace App\Referentiel\Infrastructure\External;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FrankfurterClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $apiUrl,
        private readonly int $cacheTtl,
    ) {
    }

    /**
     * @param list<string> $quotes
     *
     * @return array{date: string|null, base: string, rates: list<array{quote: string, rate: float, date: string}>}
     */
    public function getLatestRates(string $base, array $quotes): array
    {
        $base = strtoupper($base);
        $quotes = array_values(array_unique(array_map('strtoupper', $quotes)));
        $quotes = array_values(array_filter($quotes, static fn (string $q) => $q !== $base));

        if ([] === $quotes) {
            return ['date' => null, 'base' => $base, 'rates' => []];
        }

        $cacheKey = sprintf('frankfurter.latest.%s.%s', $base, implode(',', $quotes));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($base, $quotes) {
            $item->expiresAfter($this->cacheTtl);

            $rates = [];
            $latestDate = null;

            foreach (array_chunk($quotes, 10) as $chunk) {
                $data = $this->request('/v2/rates', [
                    'base' => $base,
                    'quotes' => implode(',', $chunk),
                ], 25);

                if (!is_array($data)) {
                    throw new HttpException(502, 'Réponse Frankfurter invalide.');
                }

                foreach ($data as $row) {
                    if (!is_array($row) || !isset($row['quote'], $row['rate'], $row['date'])) {
                        continue;
                    }
                    $rates[] = [
                        'quote' => (string) $row['quote'],
                        'rate' => (float) $row['rate'],
                        'date' => (string) $row['date'],
                    ];
                    if (null === $latestDate || $row['date'] > $latestDate) {
                        $latestDate = (string) $row['date'];
                    }
                }
            }

            return [
                'date' => $latestDate,
                'base' => $base,
                'rates' => $rates,
            ];
        });
    }

    /**
     * @return array{date: string, base: string, quote: string, rate: float}
     */
    public function getRate(string $base, string $quote): array
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            return [
                'date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'base' => $base,
                'quote' => $quote,
                'rate' => 1.0,
            ];
        }

        $cacheKey = sprintf('frankfurter.rate.%s.%s', $base, $quote);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($base, $quote) {
            $item->expiresAfter($this->cacheTtl);

            $data = $this->request(sprintf('/v2/rate/%s/%s', $base, $quote));

            if (!is_array($data) || !isset($data['rate'], $data['date'])) {
                throw new HttpException(502, 'Réponse Frankfurter invalide.');
            }

            return [
                'date' => (string) $data['date'],
                'base' => $base,
                'quote' => $quote,
                'rate' => (float) $data['rate'],
            ];
        });
    }

    /**
     * @return list<array{date: string, rate: float}>
     */
    public function getTimeSeries(string $base, string $quote, string $from, string $to): array
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        $cacheKey = sprintf('frankfurter.series.%s.%s.%s.%s', $base, $quote, $from, $to);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($base, $quote, $from, $to) {
            $item->expiresAfter($this->cacheTtl);

            $data = $this->request('/v2/rates', [
                'base' => $base,
                'quotes' => $quote,
                'from' => $from,
                'to' => $to,
            ]);

            if (!is_array($data)) {
                throw new HttpException(502, 'Réponse Frankfurter invalide.');
            }

            $points = [];
            foreach ($data as $row) {
                if (!is_array($row) || !isset($row['date'], $row['rate'])) {
                    continue;
                }
                $points[] = [
                    'date' => (string) $row['date'],
                    'rate' => (float) $row['rate'],
                ];
            }

            usort($points, static fn (array $a, array $b) => strcmp($a['date'], $b['date']));

            return $points;
        });
    }

    /** @param array<string, string> $query */
    private function request(string $path, array $query = [], int $timeout = 15): mixed
    {
        $url = rtrim($this->apiUrl, '/').$path;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $query,
                'timeout' => $timeout,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $body = $response->toArray(false);
                $message = is_array($body) && isset($body['message'])
                    ? (string) $body['message']
                    : 'Service Frankfurter indisponible.';

                throw new HttpException(
                    404 === $statusCode ? 400 : 502,
                    $message,
                );
            }

            return $response->toArray();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new HttpException(502, 'Service Frankfurter indisponible.');
        }
    }
}
