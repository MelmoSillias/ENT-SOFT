<?php

namespace App\Impression\Presentation\Api\Controller;

use App\Impression\Application\Service\InvoiceImpressionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/impressions')]
final class ImpressionController extends AbstractController
{
    #[Route('/settings', name: 'api_impressions_settings', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function settings(InvoiceImpressionService $service): JsonResponse
    {
        return $this->json($service->settings());
    }

    #[Route('/documents/{type}/{id}', name: 'api_impressions_document', methods: ['GET'])]
    #[IsGranted('finance.invoices.view')]
    public function document(string $type, string $id, Request $request, InvoiceImpressionService $service): Response
    {
        if ($type !== 'invoice') {
            return $this->json(['error' => 'Type de document non supporté.'], Response::HTTP_NOT_FOUND);
        }

        return $service->renderInvoice(
            id: $id,
            format: (string) $request->query->get('format', 'html'),
            page: (string) $request->query->get('page', 'a4'),
            orientation: (string) $request->query->get('orientation', 'portrait'),
            disposition: (string) $request->query->get('disposition', 'inline'),
        );
    }
}
