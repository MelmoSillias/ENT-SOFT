<?php

namespace App\Tests\Unit\DataImport;

use App\DataImport\Application\ImportFormat;
use PHPUnit\Framework\TestCase;

final class ImportFormatTest extends TestCase
{
    public function testUtf8BomQuotedCellsMultilineAndOptionalColumns(): void
    {
        $rows = (new ImportFormat())->parse('sites', "\xEF\xBB\xBFid_import;code;titre;description\r\nS1;ML01;\"Bamako; Centre\";\"Une ligne\nDeux lignes\"\r\n\r\nS2;ML02;Nord;\r\n");
        self::assertCount(2, $rows);
        self::assertSame('Bamako; Centre', $rows[0]['data']['titre']);
        self::assertSame('', $rows[0]['data']['latitude']);
        self::assertSame(5, $rows[1]['line']);
    }

    public function testUnknownColumnsAreRejectedRatherThanSilentlyLost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportFormat())->parse('sites', "id_import;code;titre;montant\nS1;A;Titre;15");
    }

    public function testMissingRequiredHeaderIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportFormat())->parse('sites', "code;titre\nA;Titre");
    }

    public function testDuplicateHeadersAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportFormat())->parse('clients', "id_import;titre;titre\nC1;A;B");
    }

    public function testExcessRowsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportFormat())->parse('clients', "id_import;titre\n".str_repeat("C1;Titre\n", 501));
    }

    public function testUnclosedQuotesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportFormat())->parse('clients', "id_import;titre\nC1;\"Titre\nC2;Autre titre");
    }

    public function testEscapedQuotesArePreserved(): void
    {
        $rows = (new ImportFormat())->parse('clients', "id_import;titre\nC1;\"Client \"\"exemple\"\"\"");
        self::assertSame('Client "exemple"', $rows[0]['data']['titre']);
    }
}
