<?php

namespace App\Finance\Presentation\Api\Controller;

use App\Finance\Application\Command\CreateFinancialTransaction\CreateFinancialTransactionCommand;
use App\Finance\Application\Command\CreateFinancialTransaction\CreateFinancialTransactionHandler;
use App\Finance\Application\Command\DeleteFinancialTransaction\DeleteFinancialTransactionCommand;
use App\Finance\Application\Command\DeleteFinancialTransaction\DeleteFinancialTransactionHandler;
use App\Finance\Application\Command\UpdateFinancialTransaction\UpdateFinancialTransactionCommand;
use App\Finance\Application\Command\UpdateFinancialTransaction\UpdateFinancialTransactionHandler;
use App\Finance\Application\Query\GetFinancialTransaction\GetFinancialTransactionHandler;
use App\Finance\Application\Query\GetFinancialTransaction\GetFinancialTransactionQuery;
use App\Finance\Application\Query\ListFinancialTransactions\ListFinancialTransactionsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/financial-transactions')]
final class FinancialTransactionController extends AbstractController
{
    #[Route('', name: 'api_financial_transactions_list', methods: ['GET'])]
    #[IsGranted('finance.transactions.view')]
    public function list(ListFinancialTransactionsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle());
    }

    #[Route('/stats', name: 'api_financial_transactions_stats', methods: ['GET'], priority: 10)]
    #[IsGranted('finance.transactions.view')]
    public function stats(\App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface $transactionRepository): JsonResponse
    {
        $stats = $transactionRepository->findStatsAggregates();
        $solde = $stats['incomeSum'] - $stats['expenseSum'];

        return $this->json([
            'solde' => $solde,
            'revenus' => [
                'count' => $stats['incomeCount'],
                'amount' => $stats['incomeSum'],
            ],
            'depenses' => [
                'count' => $stats['expenseCount'],
                'amount' => $stats['expenseSum'],
            ],
        ]);
    }

    #[Route('', name: 'api_financial_transactions_create', methods: ['POST'])]
    #[IsGranted('finance.transactions.create')]
    public function create(Request $request, CreateFinancialTransactionHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateFinancialTransactionCommand(
            date: $data['date'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            type: $data['type'] ?? 'expense',
            category: $data['category'] ?? 'OtherExpense',
            status: $data['status'] ?? 'pending',
            fromParty: $data['fromParty'] ?? '',
            toParty: $data['toParty'] ?? '',
            description: $data['description'] ?? null,
            clientId: $data['clientId'] ?? null,
            siteId: $data['siteId'] ?? null,
            invoiceId: $data['invoiceId'] ?? null,
            prestationId: $data['prestationId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_financial_transactions_get', methods: ['GET'])]
    #[IsGranted('finance.transactions.view')]
    public function get(string $id, GetFinancialTransactionHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetFinancialTransactionQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_financial_transactions_update', methods: ['PUT'])]
    #[IsGranted('finance.transactions.update')]
    public function update(string $id, Request $request, UpdateFinancialTransactionHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateFinancialTransactionCommand(
            id: $id,
            date: $data['date'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            type: $data['type'] ?? null,
            category: $data['category'] ?? null,
            description: $data['description'] ?? null,
            status: $data['status'] ?? null,
            fromParty: $data['fromParty'] ?? null,
            toParty: $data['toParty'] ?? null,
            clientId: $data['clientId'] ?? null,
            siteId: $data['siteId'] ?? null,
            invoiceId: $data['invoiceId'] ?? null,
            prestationId: $data['prestationId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_financial_transactions_delete', methods: ['DELETE'])]
    #[IsGranted('finance.transactions.delete')]
    public function delete(string $id, DeleteFinancialTransactionHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteFinancialTransactionCommand($id))->toArray());
    }
}
