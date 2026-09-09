<?php

namespace App\Geo\Application\Port;

use App\Geo\Domain\ValueObject\GeoPoint;

interface RoutingClientInterface
{
    /**
     * @return array{
     *   distanceMeters: float,
     *   durationSeconds: float,
     *   coordinates: list<array{lat: float, lng: float}>,
     *   profile: string
     * }
     */
    public function directions(GeoPoint $from, GeoPoint $to, string $profile = 'driving-car'): array;
}
