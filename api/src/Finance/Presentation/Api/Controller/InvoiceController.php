<?php

namespace App\Finance\Presentation\Api\Controller;

use App\Finance\Application\Command\CreateInvoice\CreateInvoiceCommand;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceHandler;
use App\Finance\Application\Command\DeleteInvoice\DeleteInvoiceCommand;
use App\Finance\Application\Command\DeleteInvoice\DeleteInvoiceHandler;
use App\Finance\Application\Command\PayInvoice\PayInvoiceCommand;
use App\Finance\Application\Command\PayInvoice\PayInvoiceHandler;
use App\Finance\Application\Command\ResetInvoice\ResetInvoiceCommand;
use App\Finance\Application\Command\ResetInvoice\ResetInvoiceHandler;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceCommand;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceHandler;
use App\Finance\Application\Query\GetInvoice\GetInvoiceHandler;
use App\Finance\Application\Query\GetInvoice\GetInvoiceQuery;
use App\Finance\Application\Query\ListInvoices\ListInvoicesHandler;
use App\Finance\Application\Service\InvoiceVatValidator;
use App\Finance\Domain\Validation\InvoicePaymentTerms;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/invoices')]
final class InvoiceController extends AbstractController
{
    #[Route('', name: 'api_invoices_list', methods: ['GET'])]
    #[IsGranted('finance.invoices.view')]
    public function list(Request $request, ListInvoicesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        ));
    }

    #[Route('', name: 'api_invoices_create', methods: ['POST'])]
    #[IsGranted('finance.invoices.create')]
    public function create(Request $request, CreateInvoiceHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        InvoiceVatValidator::validatePayload($data);
        $result = $handler->handle(new CreateInvoiceCommand(
            date: $data['date'] ?? '',
            clientId: $data['clientId'] ?? '',
            status: $data['status'] ?? 'draft',
            projectId: $data['projectId'] ?? null,
            projectLabel: $data['projectLabel'] ?? null,
            lines: $data['lines'] ?? [],
            vatEnabled: $data['vatEnabled'] ?? false,
            vatRate: $data['vatRate'] ?? 18,
            paymentTerms: $this->paymentTermsFromRequest($request, $data) ?? [],
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}/pay', name: 'api_invoices_pay', methods: ['POST'])]
    #[IsGranted('finance.transactions.create')]
    public function pay(string $id, Request $request, PayInvoiceHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new PayInvoiceCommand(
            id: $id,
            date: $data['date'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            description: $data['description'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}/reset', name: 'api_invoices_reset', methods: ['POST'])]
    #[IsGranted('finance.invoices.update')]
    public function reset(string $id, ResetInvoiceHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ResetInvoiceCommand($id))->toArray());
    }

    #[Route('/{id}', name: 'api_invoices_get', methods: ['GET'])]
    #[IsGranted('finance.invoices.view')]
    public function get(string $id, GetInvoiceHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetInvoiceQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_invoices_update', methods: ['PUT'])]
    #[IsGranted('finance.invoices.update')]
    public function update(string $id, Request $request, UpdateInvoiceHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        InvoiceVatValidator::validatePayload($data);
        $result = $handler->handle(new UpdateInvoiceCommand(
            id: $id,
            date: $data['date'] ?? null,
            status: $data['status'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: array_key_exists('projectId', $data) ? ($data['projectId'] ?? '') : null,
            projectLabel: array_key_exists('projectLabel', $data) ? ($data['projectLabel'] ?? '') : null,
            lines: $data['lines'] ?? null,
            updateProject: array_key_exists('projectId', $data) || array_key_exists('projectLabel', $data),
            vatEnabled: $data['vatEnabled'] ?? null,
            vatRate: $data['vatRate'] ?? null,
            paymentTerms: $this->paymentTermsFromRequest($request, $data),
        ));

        return $this->json($result->toArray());
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>|null
     */
    private function paymentTermsFromRequest(Request $request, array $data): ?array
    {
        if (!array_key_exists('paymentTerms', $data)) {
            return null;
        }

        // Le décodage objet distingue les listes JSON des objets, même vides.
        $payload = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);

        return InvoicePaymentTerms::normalize($payload->paymentTerms);
    }

    #[Route('/{id}', name: 'api_invoices_delete', methods: ['DELETE'])]
    #[IsGranted('finance.invoices.delete')]
    public function delete(string $id, DeleteInvoiceHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteInvoiceCommand($id))->toArray());
    }
}
