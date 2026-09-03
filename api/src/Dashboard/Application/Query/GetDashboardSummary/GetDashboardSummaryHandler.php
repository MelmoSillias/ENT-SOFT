<?php

namespace App\Dashboard\Application\Query\GetDashboardSummary;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Task\Domain\Repository\TaskRepositoryInterface;

final class GetDashboardSummaryHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(GetDashboardSummaryQuery $query = new GetDashboardSummaryQuery()): array
    {
        return [
            'activeProjects' => $this->projectRepository->countByStatus(ProjectStatus::ACTIVE),
            'tasksToday' => $this->taskRepository->countDueToday(),
            'clients' => $this->clientRepository->countEnabled(),
            'unpaidInvoices' => $this->countUnpaidInvoices(),
        ];
    }

    private function countUnpaidInvoices(): int
    {
        $count = 0;
        foreach ($this->invoiceRepository->findAllEnabled() as $invoice) {
            if ($invoice->getStatus() !== InvoiceStatus::INVOICED) {
                continue;
            }
            $paid = 0.0;
            foreach ($this->transactionRepository->findEnabledPaymentsByInvoiceId($invoice->getId()) as $payment) {
                $paid += $payment->getAmount();
            }
            if ($paid < $invoice->getAmount()) {
                ++$count;
            }
        }

        return $count;
    }
}
