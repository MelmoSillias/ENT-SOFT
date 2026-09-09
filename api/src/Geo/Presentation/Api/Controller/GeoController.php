<?php

namespace App\Geo\Presentation\Api\Controller;

use App\Geo\Application\Port\GeocodingClientInterface;
use App\Geo\Application\Port\RoutingClientInterface;
use App\Geo\Domain\ValueObject\GeoPoint;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/geo')]
final class GeoController extends AbstractController
{
    public function __construct(
        private readonly GeocodingClientInterface $geocodingClient,
        private readonly RoutingClientInterface $routingClient,
    ) {
    }

    #[Route('/geocode', name: 'api_geo_geocode', methods: ['GET'])]
    #[IsGranted('geo.use')]
    public function geocode(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if ('' === $q) {
            throw new \InvalidArgumentException('Le paramètre « q » est obligatoire.');
        }

        $limit = (int) $request->query->get('limit', 5);

        return $this->json([
            'items' => $this->geocodingClient->search($q, $limit),
        ]);
    }

    #[Route('/reverse', name: 'api_geo_reverse', methods: ['GET'])]
    #[IsGranted('geo.use')]
    public function reverse(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        if (null === $lat || null === $lng || '' === $lat || '' === $lng) {
            throw new \InvalidArgumentException('Les paramètres « lat » et « lng » sont obligatoires.');
        }

        $point = new GeoPoint((float) $lat, (float) $lng);
        $result = $this->geocodingClient->reverse($point);

        return $this->json([
            'item' => $result,
        ]);
    }

    #[Route('/directions', name: 'api_geo_directions', methods: ['POST'])]
    #[IsGranted('geo.use')]
    public function directions(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $profile = (string) ($data['profile'] ?? 'driving-car');

        if (!is_array($from) || !isset($from['lat'], $from['lng'])) {
            throw new \InvalidArgumentException('Le champ « from » doit contenir lat et lng.');
        }
        if (!is_array($to) || !isset($to['lat'], $to['lng'])) {
            throw new \InvalidArgumentException('Le champ « to » doit contenir lat et lng.');
        }

        $result = $this->routingClient->directions(
            new GeoPoint((float) $from['lat'], (float) $from['lng']),
            new GeoPoint((float) $to['lat'], (float) $to['lng']),
            $profile,
        );

        return $this->json($result, Response::HTTP_OK);
    }
}
