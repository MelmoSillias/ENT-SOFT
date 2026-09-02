<?php

namespace App\Referentiel\Presentation\Api\Controller;

use App\Referentiel\Application\Query\CompareExchangeRate\CompareExchangeRateHandler;
use App\Referentiel\Application\Query\ConvertExchangeRate\ConvertExchangeRateHandler;
use App\Referentiel\Application\Query\GetExchangeRateSeries\GetExchangeRateSeriesHandler;
use App\Referentiel\Application\Query\GetLatestExchangeRates\GetLatestExchangeRatesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/exchange-rates')]
final class ExchangeRateController extends AbstractController
{
    #[Route('/latest', name: 'api_exchange_rates_latest', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function latest(Request $request, GetLatestExchangeRatesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(
            base: (string) $request->query->get('base', 'EUR'),
            quotesParam: $request->query->get('quotes'),
        ));
    }

    #[Route('/compare', name: 'api_exchange_rates_compare', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function compare(Request $request, CompareExchangeRateHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(
            base: (string) $request->query->get('base', 'EUR'),
            quote: (string) $request->query->get('quote', 'XAF'),
        ));
    }

    #[Route('/convert', name: 'api_exchange_rates_convert', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function convert(Request $request, ConvertExchangeRateHandler $handler): JsonResponse
    {
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');
        $amount = (string) $request->query->get('amount', '');

        if ('' === $from || '' === $to || '' === $amount) {
            return $this->json(
                ['error' => 'Les paramètres from, to et amount sont requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return $this->json($handler->handle($from, $to, $amount));
    }

    #[Route('/series', name: 'api_exchange_rates_series', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function series(Request $request, GetExchangeRateSeriesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(
            base: (string) $request->query->get('base', 'EUR'),
            quote: (string) $request->query->get('quote', 'XAF'),
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        ));
    }
}
