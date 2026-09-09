<?php

namespace App\Geo\Domain\ValueObject;

final readonly class GeoPoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new \InvalidArgumentException('La latitude doit être comprise entre -90 et 90.');
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException('La longitude doit être comprise entre -180 et 180.');
        }
    }

    public static function tryFrom(?float $latitude, ?float $longitude): ?self
    {
        if (null === $latitude && null === $longitude) {
            return null;
        }
        if (null === $latitude || null === $longitude) {
            throw new \InvalidArgumentException('Latitude et longitude doivent être fournies ensemble.');
        }

        return new self($latitude, $longitude);
    }

    /** @return array{lat: float, lng: float} */
    public function toArray(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
        ];
    }
}
