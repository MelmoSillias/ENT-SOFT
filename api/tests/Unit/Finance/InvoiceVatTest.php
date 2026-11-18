<?php

namespace App\Tests\Unit\Finance;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceCommand;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceHandler;
use App\Finance\Application\Command\PayInvoice\PayInvoiceCommand;
use App\Finance\Application\Command\PayInvoice\PayInvoiceHandler;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceCommand;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceHandler;
use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceLineWriter;
use App\Finance\Application\Service\InvoiceNumberGenerator;
use App\Finance\Application\Service\InvoiceNumberResolver;
use App\Finance\Application\Service\InvoiceVatValidator;
use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Exception\InvoiceCannotBeModifiedException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceLineRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceMonthlySequenceRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Referentiel\Domain\Repository\ReferenceSequenceRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceVatTest extends TestCase
{
    private InvoiceRepositoryInterface&MockObject $invoices;
    private InvoiceLineRepositoryInterface&MockObject $lines;
    private FinancialTransactionRepositoryInterface&MockObject $transactions;
    private SettingRepositoryInterface&MockObject $settings;
    private InvoiceAssembler $assembler;
    private InvoiceLineWriter $writer;

    protected function setUp(): void
    {
        $this->invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $this->lines = $this->createMock(InvoiceLineRepositoryInterface::class);
        $this->transactions = $this->createMock(FinancialTransactionRepositoryInterface::class);
        $this->settings = $this->createMock(SettingRepositoryInterface::class);
        $this->assembler = new InvoiceAssembler($this->lines, $this->transactions, new InvoiceNumberResolver($this->settings));
        $this->writer = new InvoiceLineWriter($this->lines, $this->invoices);
    }

    private function invoice(float $amount = 1000): Invoice
    {
        return new Invoice('1', 'ENT1/11-2026', new \DateTimeImmutable('2026-11-18'), $amount, Uuid::v4());
    }

    public function testLegacyInvoiceKeepsAmountAndDefaults(): void
    {
        $invoice = $this->invoice(123.456);
        $data = InvoiceResponseDto::fromEntity($invoice, '1')->toArray();
        self::assertFalse($data['vatEnabled']);
        self::assertSame(18.0, $data['vatRate']);
        self::assertSame(123.456, $data['subtotal']);
        self::assertSame(0.0, $data['vatAmount']);
        self::assertSame(123.456, $data['amount']);
        self::assertSame($data['amount'], $data['total']);
    }

    #[DataProvider('creationOptions')]
    public function testCreationCalculatesTotals(array $options, float $vat, float $total): void
    {
        $sequence = $this->createMock(ReferenceSequenceRepositoryInterface::class);
        $sequence->method('getAndIncrement')->willReturn(1);
        $monthly = $this->createMock(InvoiceMonthlySequenceRepositoryInterface::class);
        $monthly->method('getAndIncrement')->willReturn(1);
        $handler = new CreateInvoiceHandler(
            $this->invoices,
            new InvoiceNumberGenerator($sequence, $monthly, $this->settings),
            $this->writer,
            $this->assembler,
        );
        $data = $handler->handle(new CreateInvoiceCommand(...[
            'date' => '2026-11-18',
            'clientId' => (string) Uuid::v4(),
            'lines' => [['description' => 'Service', 'quantity' => 2, 'unitPrice' => 500]],
            ...$options,
        ]))->toArray();
        self::assertSame(1000.0, $data['subtotal']);
        self::assertSame($vat, $data['vatAmount']);
        self::assertSame($total, $data['amount']);
        self::assertSame($total, $data['total']);
        self::assertSame($options['vatEnabled'] ?? false, $data['vatEnabled']);
        self::assertSame((float) ($options['vatRate'] ?? 18), $data['vatRate']);
    }

    public static function creationOptions(): iterable
    {
        yield 'old client' => [[], 0.0, 1000.0];
        yield 'default rate' => [['vatEnabled' => true], 180.0, 1180.0];
        yield 'disabled custom rate' => [['vatEnabled' => false, 'vatRate' => 20], 0.0, 1000.0];
        yield 'fractional rate' => [['vatEnabled' => true, 'vatRate' => 5.5], 55.0, 1055.0];
        yield 'zero' => [['vatEnabled' => true, 'vatRate' => 0], 0.0, 1000.0];
        yield 'maximum' => [['vatEnabled' => true, 'vatRate' => 100], 1000.0, 2000.0];
    }

    public function testVatIsRoundedAndNeverCompounded(): void
    {
        $invoice = $this->invoice(0.03);
        $invoice->setVat(true, 18);
        self::assertSame(0.01, $invoice->getVatAmount());
        self::assertEqualsWithDelta(0.04, $invoice->getAmount(), 0.000001);
        $invoice->setVat(true, 18);
        self::assertSame(0.03, $invoice->getSubtotal());
        self::assertEqualsWithDelta(0.04, $invoice->getAmount(), 0.000001);
    }

    public function testUpdatesRecalculateAndPreserveOmittedVatFields(): void
    {
        $invoice = $this->invoice();
        $this->invoices->method('findById')->willReturn($invoice);
        $handler = new UpdateInvoiceHandler($this->invoices, $this->transactions, $this->writer, $this->assembler);
        $id = (string) $invoice->getId();
        self::assertSame(1180.0, $handler->handle(new UpdateInvoiceCommand($id, vatEnabled: true))->amount);
        self::assertSame(1200.0, $handler->handle(new UpdateInvoiceCommand($id, vatRate: 20))->amount);
        self::assertSame(1200.0, $handler->handle(new UpdateInvoiceCommand($id, status: 'draft'))->amount);
        $updated = $handler->handle(new UpdateInvoiceCommand($id, lines: [
            ['description' => 'Nouveau service', 'quantity' => 3, 'unitPrice' => 100],
        ]));
        self::assertSame(300.0, $updated->subtotal);
        self::assertSame(60.0, $updated->vatAmount);
        self::assertSame(360.0, $updated->amount);
        $disabled = $handler->handle(new UpdateInvoiceCommand($id, vatEnabled: false));
        self::assertSame(300.0, $disabled->amount);
        self::assertSame(20.0, $disabled->vatRate);
        self::assertSame(0.0, $disabled->vatAmount);
    }

    #[DataProvider('invalidPayloads')]
    public function testStrictPayloadValidation(array $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InvoiceVatValidator::validatePayload($payload);
    }

    public static function invalidPayloads(): iterable
    {
        foreach ([null, 0, 1, 'false', 'true', [], new \stdClass()] as $value) {
            yield [['vatEnabled' => $value]];
        }
        foreach ([null, true, false, '18', '', [], -0.01, 100.01, INF, NAN] as $value) {
            yield [['vatRate' => $value]];
        }
    }

    public function testInvalidCreationIsRejectedBeforeSaving(): void
    {
        $this->invoices->expects(self::never())->method('save');
        $handler = new CreateInvoiceHandler(
            $this->invoices,
            new InvoiceNumberGenerator(
                $this->createMock(ReferenceSequenceRepositoryInterface::class),
                $this->createMock(InvoiceMonthlySequenceRepositoryInterface::class),
                $this->settings,
            ),
            $this->writer,
            $this->assembler,
        );
        $this->expectException(\InvalidArgumentException::class);
        $handler->handle(new CreateInvoiceCommand('2026-11-18', (string) Uuid::v4(), vatEnabled: 'false'));
    }

    public function testInvalidUpdateDoesNotMutateInvoice(): void
    {
        $invoice = $this->invoice();
        $this->invoices->method('findById')->willReturn($invoice);
        $this->invoices->expects(self::never())->method('save');
        $handler = new UpdateInvoiceHandler($this->invoices, $this->transactions, $this->writer, $this->assembler);
        try {
            $handler->handle(new UpdateInvoiceCommand((string) $invoice->getId(), vatEnabled: true, vatRate: 101));
            self::fail('Un taux invalide doit être refusé.');
        } catch (\InvalidArgumentException) {
            self::assertFalse($invoice->isVatEnabled());
            self::assertSame(1000.0, $invoice->getAmount());
        }
    }

    public function testPaymentUsesGrossAmountAndLocksVatChanges(): void
    {
        $invoice = $this->invoice();
        $invoice->setVat(true, 18);
        $this->invoices->method('findById')->willReturn($invoice);
        $payment = null;

        $this->transactions->expects(self::once())->method('save')->willReturnCallback(
            static function (FinancialTransaction $saved) use (&$payment): void { $payment = $saved; },
        );
        // La réponse du paiement relit les transactions après leur enregistrement.
        $this->transactions->method('findEnabledPaymentsByInvoiceId')->willReturnCallback(
            static function () use (&$payment): array { return $payment === null ? [] : [$payment]; },
        );
        $handler = new PayInvoiceHandler(
            $this->invoices,
            $this->transactions,
            $this->createMock(ClientRepositoryInterface::class),
            $this->settings,
            $this->assembler,
        );
        $response = $handler->handle(new PayInvoiceCommand(
            id: (string) $invoice->getId(), date: '2026-11-18', amount: $invoice->getAmount(),
        ));
        self::assertSame(1180.0, $payment->getAmount());
        self::assertSame(1180.0, $response->amount);
        self::assertSame(1180.0, $response->paidAmount);
        self::assertTrue($response->hasPayments);
        $this->expectException(InvoiceCannotBeModifiedException::class);
        (new UpdateInvoiceHandler($this->invoices, $this->transactions, $this->writer, $this->assembler))
            ->handle(new UpdateInvoiceCommand((string) $invoice->getId(), vatEnabled: false));
    }
}
