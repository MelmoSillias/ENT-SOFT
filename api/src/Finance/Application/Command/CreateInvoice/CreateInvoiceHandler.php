<?php

namespace App\Finance\Application\Command\CreateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceLineWriter;
use App\Finance\Application\Service\InvoiceNumberGenerator;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly InvoiceLineWriter $lineWriter,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    public function handle(CreateInvoiceCommand $command): InvoiceResponseDto
    {
        $date = new \DateTimeImmutable($command->date);
        $numbers = $this->numberGenerator->generate($date);
        $projectLabel = $command->projectLabel !== null ? trim($command->projectLabel) : null;
        if ($projectLabel === '') {
            $projectLabel = null;
        }
        $invoice = new Invoice(
            number: $numbers['sequential'],
            numberMonthly: $numbers['monthly'],
            date: $date,
            amount: 0,
            clientId: Uuid::fromString($command->clientId),
            status: InvoiceStatus::from($command->status),
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
            projectLabel: $command->projectId ? null : $projectLabel,
        );
        $this->invoiceRepository->save($invoice);
        $this->lineWriter->replaceLines($invoice, $command->lines);

        return $this->assembler->toDto($invoice);
    }
}
