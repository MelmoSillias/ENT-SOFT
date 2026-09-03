<?php

namespace App\Finance\Application\Command\CreateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceLineWriter;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use Symfony\Component\Uid\Uuid;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly CodeGeneratorService $codeGenerator,
        private readonly InvoiceLineWriter $lineWriter,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    public function handle(CreateInvoiceCommand $command): InvoiceResponseDto
    {
        $number = $this->codeGenerator->generate(ReferenceSequenceType::INVOICE);
        $invoice = new Invoice(
            number: $number,
            date: new \DateTimeImmutable($command->date),
            amount: 0,
            clientId: Uuid::fromString($command->clientId),
            status: InvoiceStatus::from($command->status),
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
        );
        $this->invoiceRepository->save($invoice);
        $this->lineWriter->replaceLines($invoice, $command->lines);

        return $this->assembler->toDto($invoice);
    }
}
