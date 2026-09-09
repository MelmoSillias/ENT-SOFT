<?php

namespace App\Geo\Infrastructure\Http;

use App\Geo\Application\Port\GeocodingClientInterface;
use App\Geo\Domain\ValueObject\GeoPoint;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Utilise OpenRouteService si ORS_API_KEY est configuré, sinon Nominatim.
 * En cas d'échec ORS (réseau / quota), bascule sur Nominatim pour search/reverse.
 */
final class FallbackGeocodingClient implements GeocodingClientInterface
{
    public function __construct(
        private readonly OpenRouteServiceClient $openRouteService,
        private readonly NominatimGeocodingClient $nominatim,
        #[Autowire('%env(ORS_API_KEY)%')]
        private readonly string $orsApiKey,
    ) {
    }

    public function search(string $query, int $limit = 5): array
    {
        if ($this->orsConfigured()) {
            try {
                return $this->openRouteService->search($query, $limit);
            } catch (HttpException $e) {
                if ($e->getStatusCode() < 500 && 503 !== $e->getStatusCode()) {
                    throw $e;
                }
            }
        }

        return $this->nominatim->search($query, $limit);
    }

    public function reverse(GeoPoint $point): ?array
    {
        if ($this->orsConfigured()) {
            try {
                return $this->openRouteService->reverse($point);
            } catch (HttpException $e) {
                if ($e->getStatusCode() < 500 && 503 !== $e->getStatusCode()) {
                    throw $e;
                }
            }
        }

        return $this->nominatim->reverse($point);
    }

    private function orsConfigured(): bool
    {
        return '' !== trim($this->orsApiKey);
    }
}
