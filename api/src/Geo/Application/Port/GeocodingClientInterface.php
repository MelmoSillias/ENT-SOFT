<?php

namespace App\Geo\Application\Port;

use App\Geo\Domain\ValueObject\GeoPoint;

interface GeocodingClientInterface
{
    /**
     * @return list<array{label: string, lat: float, lng: float}>
     */
    public function search(string $query, int $limit = 5): array;

    /**
     * @return array{label: string, lat: float, lng: float}|null
     */
    public function reverse(GeoPoint $point): ?array;
}
