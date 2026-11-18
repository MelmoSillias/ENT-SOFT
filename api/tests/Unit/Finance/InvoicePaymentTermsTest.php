<?php

namespace App\Tests\Unit\Finance;

use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceCommand;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceHandler;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceCommand;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceHandler;
use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceLineWriter;
use App\Finance\Application\Service\InvoiceNumberGenerator;
use App\Finance\Application\Service\InvoiceNumberResolver;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceLineRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceMonthlySequenceRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Finance\Domain\Validation\InvoicePaymentTerms;
use App\Finance\Presentation\Api\Controller\InvoiceController;
use App\Referentiel\Domain\Repository\ReferenceSequenceRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class InvoicePaymentTermsTest extends TestCase
{
    private InvoiceRepositoryInterface&MockObject $invoices;
    private CreateInvoiceHandler $create;
    private UpdateInvoiceHandler $update;
    private InvoiceController $controller;
    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $lines = $this->createMock(InvoiceLineRepositoryInterface::class);
        $transactions = $this->createMock(FinancialTransactionRepositoryInterface::class);
        $settings = $this->createMock(SettingRepositoryInterface::class);
        $assembler = new InvoiceAssembler($lines, $transactions, new InvoiceNumberResolver($settings));
        $writer = new InvoiceLineWriter($lines, $this->invoices);
        $sequence = $this->createMock(ReferenceSequenceRepositoryInterface::class);
        $sequence->method('getAndIncrement')->willReturn(1);
        $monthly = $this->createMock(InvoiceMonthlySequenceRepositoryInterface::class);
        $monthly->method('getAndIncrement')->willReturn(1);
        $this->create = new CreateInvoiceHandler(
            $this->invoices, new InvoiceNumberGenerator($sequence, $monthly, $settings), $writer, $assembler,
        );
        $this->update = new UpdateInvoiceHandler($this->invoices, $transactions, $writer, $assembler);
        $this->invoice = new Invoice('1', 'ENT1/11-2026', new \DateTimeImmutable('2026-11-18'), 1000, Uuid::v4());
        $this->invoices->method('findById')->willReturn($this->invoice);
        $this->controller = new InvoiceController();
        $this->controller->setContainer(new Container());
    }

    public function testDefaultsAndLegacyNullAreExposedAsEmptyLists(): void
    {
        self::assertSame([], $this->invoice->getPaymentTerms());
        self::assertSame([], InvoiceResponseDto::fromEntity($this->invoice, '1')->toArray()['paymentTerms']);
        (new \ReflectionProperty(Invoice::class, 'paymentTerms'))->setValue($this->invoice, null);
        self::assertSame([], $this->invoice->getPaymentTerms());
        self::assertSame([], InvoiceResponseDto::fromEntity($this->invoice, '1')->toArray()['paymentTerms']);
    }

    #[DataProvider('creationOptions')]
    public function testCreationNormalizesAndReturnsPaymentTerms(array $options, array $expected): void
    {
        $data = $this->create->handle(new CreateInvoiceCommand(...[
            'date' => '2026-11-18', 'clientId' => (string) Uuid::v4(), ...$options,
        ]))->toArray();
        self::assertSame($expected, $data['paymentTerms']);
    }

    public static function creationOptions(): iterable
    {
        yield 'omitted' => [[], []];
        yield 'empty' => [['paymentTerms' => []], []];
        yield 'trim and filter without deduplication' => [
            ['paymentTerms' => ['  Acompte de 30 %  ', '', " \t\n", ' Solde à 30 jours ', 'Solde à 30 jours']],
            ['Acompte de 30 %', 'Solde à 30 jours', 'Solde à 30 jours'],
        ];
    }

    public function testUpdateReplacesPreservesAndClearsTerms(): void
    {
        $id = (string) $this->invoice->getId();
        $this->invoice->setPaymentTerms(['Ancienne condition']);
        self::assertSame(['Ancienne condition'], $this->update->handle(new UpdateInvoiceCommand($id))->paymentTerms);
        $updated = $this->update->handle(new UpdateInvoiceCommand($id, paymentTerms: ['  À réception ', '', '  ']));
        self::assertSame(['À réception'], $updated->toArray()['paymentTerms']);
        self::assertSame(['À réception'], $this->invoice->getPaymentTerms());
        self::assertSame([], $this->update->handle(new UpdateInvoiceCommand($id, paymentTerms: []))->paymentTerms);
        self::assertSame([], $this->invoice->getPaymentTerms());
    }

    public function testControllerForwardsCreateAndUpdateAndDistinguishesOmission(): void
    {
        $request = $this->request([
            'date' => '2026-11-18', 'clientId' => (string) Uuid::v4(), 'paymentTerms' => ['  À réception ', ''],
        ]);
        $response = $this->controller->create($request, $this->create);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['À réception'], json_decode($response->getContent(), true)['paymentTerms']);
        $response = $this->controller->create($this->request([
            'date' => '2026-11-18', 'clientId' => (string) Uuid::v4(),
        ]), $this->create);
        self::assertSame([], json_decode($response->getContent(), true)['paymentTerms']);

        $id = (string) $this->invoice->getId();
        $this->invoice->setPaymentTerms(['Conserver']);

        $response = $this->controller->update($id, $this->request(['status' => 'draft']), $this->update);
        self::assertSame(['Conserver'], json_decode($response->getContent(), true)['paymentTerms']);
        $response = $this->controller->update($id, $this->request(['paymentTerms' => [' Nouveau ', '']]), $this->update);
        self::assertSame(['Nouveau'], json_decode($response->getContent(), true)['paymentTerms']);
        $response = $this->controller->update($id, $this->request(['paymentTerms' => []]), $this->update);
        self::assertSame([], json_decode($response->getContent(), true)['paymentTerms']);
    }

    #[DataProvider('invalidTerms')]
    public function testInvalidTermsAreRejected(mixed $terms): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('paymentTerms');
        InvoicePaymentTerms::normalize($terms);
    }

    #[DataProvider('invalidTerms')]
    public function testInvalidCreationIsRejectedBeforeSaving(mixed $terms): void
    {
        $this->invoices->expects(self::never())->method('save');
        $this->expectException(\InvalidArgumentException::class);
        $this->create->handle(new CreateInvoiceCommand('2026-11-18', (string) Uuid::v4(), paymentTerms: $terms));
    }

    public function testInvalidUpdateDoesNotMutateInvoice(): void
    {
        $this->invoice->setPaymentTerms(['Conserver']);
        $before = $this->invoice->getUpdatedAt();
        $this->invoices->expects(self::never())->method('save');
        try {
            $this->update->handle(new UpdateInvoiceCommand(
                (string) $this->invoice->getId(), status: 'sent', vatEnabled: true, paymentTerms: ['Valide', 10],
            ));
            self::fail('Une condition non textuelle doit être refusée.');
        } catch (\InvalidArgumentException) {
            self::assertSame(['Conserver'], $this->invoice->getPaymentTerms());
            self::assertFalse($this->invoice->isVatEnabled());
            self::assertSame(1000.0, $this->invoice->getAmount());
            self::assertSame($before, $this->invoice->getUpdatedAt());
        }
    }

    #[DataProvider('invalidTerms')]
    public function testControllerRejectsInvalidJsonTypesOnCreateAndUpdate(mixed $terms): void
    {
        $this->invoices->expects(self::never())->method('save');
        foreach (['create', 'update'] as $action) {
            $request = $this->request(['paymentTerms' => $terms]);
            try {
                if ($action === 'create') {
                    $this->controller->create($request, $this->create);
                } else {
                    $this->controller->update((string) $this->invoice->getId(), $request, $this->update);
                }
                self::fail('Le type JSON invalide doit être refusé.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('paymentTerms', $exception->getMessage());
            }
        }
    }

    public static function invalidTerms(): iterable
    {
        foreach ([null, false, true, 0, 1.5, 'À réception', new \stdClass(), (object) ['0' => 'texte'],
            ['condition' => 'texte'], [1 => 'texte'], [null], [false], [10], [1.5], [[]], [new \stdClass()],
            ['Valide', ['text' => 'objet interdit']]] as $value) {
            yield [$value];
        }
    }

    private function request(array $payload): Request
    {
        return Request::create('/api/invoices', 'POST', content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
