<?php

namespace App\Impression\Presentation\Api\Controller;

use App\Impression\Application\Service\InvoiceImpressionService;
use App\Impression\Application\Service\TableImpressionService;
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

    #[Route('/tables/{tableType}/print', name: 'api_impressions_table_print', methods: ['POST'])]
    #[IsGranted('impression.documents.print')]
    public function printTable(string $tableType, Request $request, TableImpressionService $service): Response
    {
        $payload = $this->decodePayload($request);
        if ($payload === null) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        return $service->render(
            tableType: $tableType,
            payload: $payload,
            format: 'html',
            disposition: 'inline',
        );
    }

    #[Route('/tables/{tableType}/export', name: 'api_impressions_table_export', methods: ['POST'])]
    #[IsGranted('impression.tables.export')]
    public function exportTable(string $tableType, Request $request, TableImpressionService $service): Response
    {
        $payload = $this->decodePayload($request);
        if ($payload === null) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $format = strtolower((string) ($payload['format'] ?? 'pdf'));
        if (!in_array($format, ['html', 'pdf', 'excel', 'csv', 'word'], true)) {
            return $this->json(['error' => 'Format non supporté.'], Response::HTTP_BAD_REQUEST);
        }

        return $service->render(
            tableType: $tableType,
            payload: $payload,
            format: $format,
            disposition: $format === 'html' ? 'inline' : 'attachment',
        );
    }

    /** @return array<string, mixed>|null */
    private function decodePayload(Request $request): ?array
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
