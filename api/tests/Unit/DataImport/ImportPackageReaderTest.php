<?php

namespace App\Tests\Unit\DataImport;

use App\DataImport\Application\ImportPackageReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportPackageReaderTest extends TestCase
{
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) { unlink($path); }
    }

    private function archive(array $entries): UploadedFile
    {
        $directory = dirname(__DIR__, 3).'/var';
        if (!is_dir($directory)) { mkdir($directory, 0775, true); }
        $path = $directory.'/import_test_'.bin2hex(random_bytes(6)).'.zip';
        $this->temporary[] = $path;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) { $zip->addFromString($name, $content); }
        $zip->close();
        return new UploadedFile($path, 'depenses.zip', 'application/zip', null, true);
    }

    public function testReadsCsvAndPdfWithoutExtractingPaths(): void
    {
        $package = (new ImportPackageReader())->read($this->archive(['donnees.csv' => 'id_import;date', 'pieces/recu.pdf' => "%PDF-1.4\n%%EOF"]));
        self::assertSame('id_import;date', $package['csv']);
        self::assertArrayHasKey('pieces/recu.pdf', $package['files']);
    }

    public function testRejectsZipTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportPackageReader())->read($this->archive(['donnees.csv' => 'a', 'pieces/../../evil.php' => '<?php']));
    }

    public function testRejectsDisguisedExecutable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportPackageReader())->read($this->archive(['donnees.csv' => 'a', 'pieces/photo.jpg' => '<?php echo 1;']));
    }

    public function testRejectsExpandedOversizeFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImportPackageReader())->read($this->archive(['donnees.csv' => 'a', 'pieces/large.txt' => str_repeat('a', ImportPackageReader::MAX_FILE + 1)]));
    }
}
