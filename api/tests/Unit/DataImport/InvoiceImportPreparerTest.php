<?php

namespace App\Tests\Unit\DataImport;

use App\DataImport\Application\InvoiceImportPreparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceImportPreparerTest extends TestCase
{
    private static function row(array $overrides = []): array
    {
        return array_replace([
            'date' => '2026-11-20',
            'lignes' => '[{"description":" Service ","quantity":"2,5","unitPrice":100},{"description":"Remise","quantity":1,"unitPrice":-10,"unit":""}]',
        ], $overrides);
    }

    public function testDefaultsAndMultipleLinesPreserveDomainSemantics(): void
    {
        $id = (string) Uuid::v4();
        $command = (new InvoiceImportPreparer())->prepare(self::row(), $id);
        self::assertSame($id, $command->clientId);
        self::assertSame('draft', $command->status);
        self::assertFalse($command->vatEnabled);
        self::assertSame(18.0, $command->vatRate);
        self::assertSame([], $command->paymentTerms);
        self::assertNull($command->projectId);
        self::assertNull($command->projectLabel);
        self::assertSame([
            ['description' => 'Service', 'quantity' => 2.5, 'unitPrice' => 100.0, 'unit' => 'Lot'],
            ['description' => 'Remise', 'quantity' => 1.0, 'unitPrice' => -10.0, 'unit' => 'Lot'],
        ], $command->lines);
    }

    public function testOptionalFieldsAreNormalized(): void
    {
        $command = (new InvoiceImportPreparer())->prepare(self::row([
            'statut' => 'invoiced', 'projet_libelle' => 'Réseau', 'tva_active' => '1', 'taux_tva' => '5,5',
            'conditions_paiement' => '[" 50 % à la commande ", "", "Solde à livraison"]',
        ]), (string) Uuid::v4());
        self::assertSame('invoiced', $command->status);
        self::assertSame('Réseau', $command->projectLabel);
        self::assertTrue($command->vatEnabled);
        self::assertSame(5.5, $command->vatRate);
        self::assertSame(['50 % à la commande', 'Solde à livraison'], $command->paymentTerms);
    }

    #[DataProvider('invalidRows')]
    public function testInvalidValuesAreRejectedDuringPreparation(array $overrides): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceImportPreparer())->prepare(self::row($overrides), (string) Uuid::v4());
    }

    public static function invalidRows(): iterable
    {
        foreach (['2026-02-30', '20/11/2026', '2026-1-01'] as $date) { yield [['date' => $date]]; }
        yield [['statut' => 'paid']];
        yield [['tva_active' => 'oui']];
        foreach (['-1', '100.1', 'NaN', 'true'] as $rate) { yield [['taux_tva' => $rate]]; }
        foreach (['{}', 'null', '[1]', '["ok",false]', '['] as $terms) { yield [['conditions_paiement' => $terms]]; }
        foreach (['[]', '{}', 'null', '[', '[1]', '[{}]', '[{"description":true,"quantity":1,"unitPrice":2}]',
            '[{"description":" ","quantity":1,"unitPrice":2}]',
            '[{"description":"A","quantity":0,"unitPrice":2}]',
            '[{"description":"A","quantity":-1,"unitPrice":2}]',
            '[{"description":"A","quantity":true,"unitPrice":2}]',
            '[{"description":"A","quantity":1,"unitPrice":"1 000"}]',
            '[{"description":"A","quantity":1,"unitPrice":null}]',
            '[{"description":"A","quantity":1,"unitPrice":2,"unit":[]}]',
            '[{"description":"A","quantity":1,"unitPrice":2,"amount":2}]',
            json_encode([['description' => str_repeat('A', 256), 'quantity' => 1, 'unitPrice' => 2]]),
            json_encode([['description' => 'A', 'quantity' => 1, 'unitPrice' => 2, 'unit' => str_repeat('A', 51)]]),
            json_encode([['description' => 'A', 'quantity' => str_repeat('9', 200), 'unitPrice' => str_repeat('9', 200)]]),
        ] as $lines) { yield [['lignes' => $lines]]; }
    }

    public function testMissingClientIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceImportPreparer())->prepare(self::row(), null);
    }
}
