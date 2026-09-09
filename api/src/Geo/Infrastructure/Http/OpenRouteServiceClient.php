<?php

namespace App\Geo\Infrastructure\Http;

use App\Geo\Application\Port\GeocodingClientInterface;
use App\Geo\Application\Port\RoutingClientInterface;
use App\Geo\Domain\ValueObject\GeoPoint;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenRouteServiceClient implements GeocodingClientInterface, RoutingClientInterface
{
    private const ALLOWED_PROFILES = [
        'driving-car',
        'foot-walking',
        'cycling-regular',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(ORS_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(ORS_API_URL)%')]
        private readonly string $apiUrl,
    ) {
    }

    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $this->assertConfigured();

        $data = $this->request('GET', '/geocode/search', [
            'query' => [
                'text' => $query,
                'size' => max(1, min(10, $limit)),
                'lang' => 'fr',
            ],
        ]);

        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $results = [];

        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($coords) || count($coords) < 2) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $label = (string) ($props['label'] ?? $props['name'] ?? '');
            if ('' === $label) {
                continue;
            }
            $results[] = [
                'label' => $label,
                'lat' => (float) $coords[1],
                'lng' => (float) $coords[0],
            ];
        }

        return $results;
    }

    public function reverse(GeoPoint $point): ?array
    {
        $this->assertConfigured();

        $data = $this->request('GET', '/geocode/reverse', [
            'query' => [
                'point.lon' => $point->longitude,
                'point.lat' => $point->latitude,
                'size' => 1,
                'lang' => 'fr',
            ],
        ]);

        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $feature = $features[0] ?? null;
        if (!is_array($feature)) {
            return null;
        }

        $coords = $feature['geometry']['coordinates'] ?? null;
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $label = (string) ($props['label'] ?? $props['name'] ?? '');
        if ('' === $label || !is_array($coords) || count($coords) < 2) {
            return null;
        }

        return [
            'label' => $label,
            'lat' => (float) $coords[1],
            'lng' => (float) $coords[0],
        ];
    }

    public function directions(GeoPoint $from, GeoPoint $to, string $profile = 'driving-car'): array
    {
        $this->assertConfigured();

        if (!in_array($profile, self::ALLOWED_PROFILES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Profil d\'itinéraire invalide. Autorisés : %s.',
                implode(', ', self::ALLOWED_PROFILES),
            ));
        }

        $data = $this->request('POST', '/v2/directions/'.$profile.'/geojson', [
            'json' => [
                'coordinates' => [
                    [$from->longitude, $from->latitude],
                    [$to->longitude, $to->latitude],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $feature = $features[0] ?? null;
        if (!is_array($feature)) {
            throw new HttpException(502, 'Réponse OpenRouteService invalide (itinéraire).');
        }

        $summary = is_array($feature['properties']['summary'] ?? null)
            ? $feature['properties']['summary']
            : [];
        $rawCoords = $feature['geometry']['coordinates'] ?? [];
        $coordinates = [];
        if (is_array($rawCoords)) {
            foreach ($rawCoords as $pair) {
                if (!is_array($pair) || count($pair) < 2) {
                    continue;
                }
                $coordinates[] = [
                    'lat' => (float) $pair[1],
                    'lng' => (float) $pair[0],
                ];
            }
        }

        return [
            'distanceMeters' => (float) ($summary['distance'] ?? 0),
            'durationSeconds' => (float) ($summary['duration'] ?? 0),
            'coordinates' => $coordinates,
            'profile' => $profile,
        ];
    }

    private function assertConfigured(): void
    {
        if ('' === trim($this->apiKey)) {
            throw new HttpException(
                503,
                'OpenRouteService n\'est pas configuré (ORS_API_KEY manquant).',
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $url = rtrim($this->apiUrl, '/').$path;
        $options['timeout'] = $options['timeout'] ?? 20;
        $options['headers'] = array_merge(
            ['Authorization' => $this->apiKey],
            is_array($options['headers'] ?? null) ? $options['headers'] : [],
        );

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->toArray(false);

            if ($statusCode >= 400) {
                $message = is_array($body) && isset($body['error']['message'])
                    ? (string) $body['error']['message']
                    : (is_array($body) && isset($body['error']) && is_string($body['error'])
                        ? $body['error']
                        : 'Service OpenRouteService indisponible.');

                throw new HttpException(
                    $statusCode >= 500 ? 502 : 400,
                    $message,
                );
            }

            return is_array($body) ? $body : [];
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new HttpException(502, 'Service OpenRouteService indisponible.');
        }
    }
}
