<?php

namespace App\Geo\Infrastructure\Http;

use App\Geo\Application\Port\GeocodingClientInterface;
use App\Geo\Domain\ValueObject\GeoPoint;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fallback géocodage via Nominatim (OpenStreetMap) — sans clé API.
 * Respecte la politique d'usage Nominatim (User-Agent identifiant l'application).
 */
final class NominatimGeocodingClient implements GeocodingClientInterface
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $data = $this->request('GET', '/search', [
            'query' => [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 0,
                'limit' => max(1, min(10, $limit)),
                'accept-language' => 'fr',
            ],
        ]);

        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['display_name'] ?? ''));
            if ('' === $label || !isset($item['lat'], $item['lon'])) {
                continue;
            }
            $lat = (float) $item['lat'];
            $lng = (float) $item['lon'];
            if (!is_finite($lat) || !is_finite($lng)) {
                continue;
            }
            $results[] = [
                'label' => $label,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return $results;
    }

    public function reverse(GeoPoint $point): ?array
    {
        $data = $this->request('GET', '/reverse', [
            'query' => [
                'lat' => $point->latitude,
                'lon' => $point->longitude,
                'format' => 'json',
                'addressdetails' => 0,
                'accept-language' => 'fr',
                'zoom' => 18,
            ],
        ]);

        if (!is_array($data)) {
            return null;
        }

        $label = trim((string) ($data['display_name'] ?? ''));
        $lat = isset($data['lat']) ? (float) $data['lat'] : $point->latitude;
        $lng = isset($data['lon']) ? (float) $data['lon'] : $point->longitude;
        if ('' === $label) {
            return null;
        }

        return [
            'label' => $label,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>|list<array<string, mixed>>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $url = self::BASE_URL.$path;
        $options['timeout'] = $options['timeout'] ?? 15;
        $options['headers'] = array_merge(
            [
                'User-Agent' => 'ENT-SOFT/1.0 (geocoding; https://github.com/ent-soft)',
                'Accept' => 'application/json',
            ],
            is_array($options['headers'] ?? null) ? $options['headers'] : [],
        );

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->toArray(false);

            if ($statusCode >= 400) {
                throw new HttpException(
                    $statusCode >= 500 ? 502 : 400,
                    'Service Nominatim indisponible.',
                );
            }

            return is_array($body) ? $body : [];
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new HttpException(502, 'Service Nominatim indisponible.');
        }
    }
}
