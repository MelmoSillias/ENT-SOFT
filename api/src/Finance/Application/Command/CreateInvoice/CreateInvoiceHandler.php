<?php

namespace App\Finance\Application\Command\CreateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
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
    ) {
    }

    public function handle(CreateInvoiceCommand $command): InvoiceResponseDto
    {
        $number = $this->codeGenerator->generate(ReferenceSequenceType::INVOICE);
        $invoice = new Invoice(
            number: $number,
            date: new \DateTimeImmutable($command->date),
            amount: $command->amount,
            clientId: Uuid::fromString($command->clientId),
            status: InvoiceStatus::from($command->status),
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
        );
        $this->invoiceRepository->save($invoice);

        return InvoiceResponseDto::fromEntity($invoice);
    }
}
