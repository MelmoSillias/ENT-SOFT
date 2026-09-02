<?php

namespace App\Finance\Presentation\Api\Controller;

use App\Finance\Application\Command\CreateInvoice\CreateInvoiceCommand;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceHandler;
use App\Finance\Application\Command\DeleteInvoice\DeleteInvoiceCommand;
use App\Finance\Application\Command\DeleteInvoice\DeleteInvoiceHandler;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceCommand;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceHandler;
use App\Finance\Application\Query\GetInvoice\GetInvoiceHandler;
use App\Finance\Application\Query\GetInvoice\GetInvoiceQuery;
use App\Finance\Application\Query\ListInvoices\ListInvoicesHandler;
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
    public function list(ListInvoicesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle());
    }

    #[Route('', name: 'api_invoices_create', methods: ['POST'])]
    #[IsGranted('finance.invoices.create')]
    public function create(Request $request, CreateInvoiceHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateInvoiceCommand(
            date: $data['date'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            clientId: $data['clientId'] ?? '',
            status: $data['status'] ?? 'draft',
            projectId: $data['projectId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
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
        $result = $handler->handle(new UpdateInvoiceCommand(
            id: $id,
            date: $data['date'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            status: $data['status'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_invoices_delete', methods: ['DELETE'])]
    #[IsGranted('finance.invoices.delete')]
    public function delete(string $id, DeleteInvoiceHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteInvoiceCommand($id))->toArray());
    }
}
