<?php

namespace App\Tests\Unit\Impression;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Configuration\Application\Service\AgenceLogoUploadService;
use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Enum\TypeValeur;
use App\Configuration\Domain\Repository\HistoriqueSettingRepositoryInterface;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceNumberResolver;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Entity\InvoiceLine;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceLineRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Impression\Application\Service\ImpressionDocumentSupport;
use App\Impression\Application\Service\InvoiceImpressionService;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Referentiel\Domain\Catalog\SettingsCatalog;
use App\SharedKernel\Application\Event\DomainEventDispatcherInterface;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class InvoiceImpressionTest extends TestCase
{
    private string $id;
    private string $lastHtml = '';
    private ImpressionDocumentSupport $support;

    private function service(?string $template = null, bool $vat = true, int $count = 2, bool $long = false, array $paymentTerms = []): InvoiceImpressionService
    {
        $values = [
            'AGENCE_NOM' => 'Agence personnalisée', 'AGENCE_ADRESSE' => 'Adresse personnalisée',
            'AGENCE_VILLE' => 'Ville test', 'AGENCE_PAYEE' => 'Bénéficiaire test',
            'AGENCE_NINA' => 'NINA-TEST', 'AGENCE_NIF_FISCAL' => 'NIF-TEST',
            'IMPRESSION_MARGIN_MM' => '10', 'IMPRESSION_FOOTER_TEXT' => 'Contact personnalisé',
            'IMPRESSION_SHOW_LOGO' => 'true',
            'AGENCE_LOGO_URL' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=',
        ];
        if ($template !== null) {
            $values['IMPRESSION_INVOICE_TEMPLATE'] = $template;
        }
        $settings = $this->createMock(SettingRepositoryInterface::class);
        $settings->method('findByCle')->willReturnCallback(static fn (string $key) => isset($values[$key]) ? new Setting($key, $values[$key], TypeValeur::STRING) : null);
        $logo = new AgenceLogoUploadService(
            $settings, $this->createMock(HistoriqueSettingRepositoryInterface::class),
            $this->createMock(DomainEventDispatcherInterface::class),
            $this->createMock(ValidatorInterface::class), dirname(__DIR__, 3),
        );
        $this->support = new ImpressionDocumentSupport($settings, $logo);
        $invoice = new Invoice('TEST-42', 'TEST42/11-2026', new \DateTimeImmutable('2026-11-18'), $count * 500, Uuid::v4());
        $invoice->setVat($vat, 18);
        $invoice->setPaymentTerms($paymentTerms);
        $this->id = (string) $invoice->getId();
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('findById')->willReturn($invoice);
        $lines = $this->createMock(InvoiceLineRepositoryInterface::class);
        $items = [];
        for ($i = 1; $i <= $count; ++$i) {
            $description = 'ARTICLE'.sprintf('%03d', $i);
            if ($long && $i === 1) {
                $description .= ' '.str_repeat('Description très longue sans troncature ', 60).'FIN_DESCRIPTION';
            }
            $items[] = new InvoiceLine($invoice, $description, 2, 250);
        }
        $lines->method('findByInvoiceId')->willReturn($items);
        $transactions = $this->createMock(FinancialTransactionRepositoryInterface::class);
        $transactions->method('findEnabledPaymentsByInvoiceId')->willReturn([]);
        $resolver = new InvoiceNumberResolver($settings);
        $realTwig = new Environment(new FilesystemLoader(dirname(__DIR__, 3).'/templates'), ['strict_variables' => true]);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function ($name, $context) use ($realTwig): string {
            return $this->lastHtml = $realTwig->render($name, $context);
        });

        return new InvoiceImpressionService(
            $invoices, new InvoiceAssembler($lines, $transactions, $resolver), $resolver,
            $this->createMock(ClientRepositoryInterface::class), $this->createMock(ProjectRepositoryInterface::class),
            $this->support, $twig,
        );
    }

    public static function models(): iterable
    {
        foreach (['classic', 'modern'] as $template) {
            foreach ([false, true] as $vat) {
                yield $template.($vat ? ' VAT' : ' no VAT') => [$template, $vat];
            }
        }
    }

    #[DataProvider('models')]
    public function testHtmlUsesConfiguredIdentityAndDtoTotals(string $template, bool $vat): void
    {
        $service = $this->service($template, $vat);
        $response = $service->renderInvoice($this->id, 'html', 'a4', 'portrait', 'attachment');
        $html = $response->getContent();
        self::assertStringContainsString('Agence personnalisée', $html);
        self::assertStringContainsString('NINA-TEST', $html);
        self::assertStringContainsString('TEST-42', $html);
        self::assertStringContainsString('ARTICLE001', $html);
        self::assertStringContainsString('Total HT', $html);
        self::assertStringContainsString('TOTAL TTC', $html);
        self::assertStringContainsString($vat ? '1 180' : '1 000', $html);
        self::assertSame($vat, str_contains($html, 'TVA (18 %)'));
        self::assertStringNotContainsString('Spiro', $html);
        self::assertStringNotContainsString('5 136 000', $html);
        self::assertStringContainsString('table-header-group', $html);
        self::assertStringContainsString('page-break-inside: avoid', $html);
        if ($template === 'modern') {
            self::assertStringContainsString('Logo Agence personnalisée', $html);
            self::assertStringContainsString('Signature et cachet', $html);
            self::assertStringContainsString('beforeprint', $html);
        }
    }

    private function modernDesignDom(string $page = 'a4', string $orientation = 'portrait'): \DOMXPath
    {
        $service = $this->service('modern');
        $html = $service->renderInvoice($this->id, 'html', $page, $orientation, 'attachment')->getContent();
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            self::assertTrue($document->loadHTML('<?xml encoding="UTF-8">'.$html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new \DOMXPath($document);
    }

    private function designCss(\DOMXPath $dom): string
    {
        $css = '';
        foreach ($dom->query('//style') as $style) {
            $css .= $style->textContent."\n";
        }

        return $css;
    }

    private function assertDesignDeclaration(string $css, string $selector, string $property, string $expected): void
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        // Inspect the desktop defaults without applying print or narrow-screen overrides.
        $css = preg_replace('/@media[^{}]*(\{(?:[^{}]+|(?1))*\})/s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $actual = null;
        foreach ($rules as $rule) {
            if (!in_array($selector, array_map('trim', explode(',', $rule[1])), true)) {
                continue;
            }
            if (preg_match_all('/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*([^;]+)/i', $rule[2], $declarations)) {
                $actual = trim(end($declarations[1]));
            }
        }
        self::assertNotNull($actual, "Déclaration CSS manquante : $selector { $property }");
        $normalize = static fn (string $value): string => preg_replace('/\s+/', ' ', str_replace(['"', "'"], '', trim($value)));
        self::assertSame($normalize($expected), $normalize($actual), "$selector { $property }");
    }

    public function testModernDesignTypographyAndBannerDimensions(): void
    {
        $dom = $this->modernDesignDom();
        $css = $this->designCss($dom);
        foreach (['body.is-portrait', 'body.is-landscape'] as $selector) {
            $this->assertDesignDeclaration($css, $selector, 'font', '13px/1.42 Arial, Helvetica, sans-serif');
        }
        $this->assertDesignDeclaration($css, '.brand-banner', 'min-height', '108px');
        $this->assertDesignDeclaration($css, '.logo-wrap', 'width', '100px');
        $this->assertDesignDeclaration($css, '.logo-wrap', 'height', '78px');
        $this->assertDesignDeclaration($css, '.logo-wrap img', 'width', '100%');
        $this->assertDesignDeclaration($css, '.logo-wrap img', 'height', '100%');
        $this->assertDesignDeclaration($css, '.logo-wrap img', 'object-fit', 'contain');
        $this->assertDesignDeclaration($css, '.company-name', 'font-size', '23px');
        $this->assertDesignDeclaration($css, '.invoice-title', 'font-size', '26px');
        self::assertSame(1, $dom->query('//*[@id="invoice-content"]//*[contains(concat(" ", normalize-space(@class), " "), " brand-banner ")]//img')->length);
        self::assertSame(1, $dom->query('//*[@id="invoice-content"]//h1[contains(concat(" ", normalize-space(@class), " "), " invoice-title ")]')->length);
    }

    public function testModernDesignTableKeepsSixProportionalColumns(): void
    {
        $dom = $this->modernDesignDom();
        $css = $this->designCss($dom);
        $this->assertDesignDeclaration($css, '.invoice-table', 'font-size', '11.5px');
        $columns = ['idx', 'designation', 'unit', 'qty', 'money', 'money'];
        $widths = ['5%', '39%', '10%', '8%', '19%', '19%'];
        $table = '//*[@id="invoice-content"]//table[contains(concat(" ", normalize-space(@class), " "), " invoice-table ")]';
        $headers = $dom->query($table.'/thead/tr/th');
        self::assertCount(6, $headers);
        $colgroup = $dom->query($table.'/colgroup/col');
        self::assertCount(6, $colgroup);
        foreach ($columns as $index => $class) {
            self::assertContains($class, preg_split('/\s+/', trim($headers->item($index)->getAttribute('class'))));
            $this->assertDesignDeclaration($css, '.invoice-table .'.$class, 'width', $widths[$index]);
            $this->assertDesignDeclaration('col { '.$colgroup->item($index)->getAttribute('style').' }', 'col', 'width', $widths[$index]);
        }
        foreach ($dom->query($table.'/tbody/tr') as $row) {
            self::assertSame(6, $dom->query('./td', $row)->length);
        }
    }

    public function testModernDesignFooterPlacesPageBetweenIdentityAndRegistration(): void
    {
        $dom = $this->modernDesignDom();
        $css = $this->designCss($dom);
        $pages = $dom->query('//*[@id="invoice-footer"]//*[contains(concat(" ", normalize-space(@class), " "), " footer-page ")]');
        self::assertCount(1, $pages);
        $grid = $pages->item(0)->parentNode;
        $children = $dom->query('./*', $grid);
        self::assertCount(3, $children, 'Le pied de page comporte identité, pagination centrale et mentions légales.');
        self::assertTrue($children->item(1)->isSameNode($pages->item(0)));
        self::assertInstanceOf(\DOMElement::class, $grid);
        $classes = preg_split('/\s+/', trim($grid->getAttribute('class')));
        self::assertNotEmpty($classes[0], 'La grille du pied de page doit être ciblable par une classe CSS.');
        $selector = '.'.$classes[0];
        $this->assertDesignDeclaration($css, $selector, 'display', 'grid');
        $this->assertDesignDeclaration($css, $selector, 'grid-template-columns', 'minmax(0,1fr) 72px minmax(0,1fr)');
        $this->assertDesignDeclaration($css, '.invoice-footer', 'height', '105px');
        $this->assertDesignDeclaration($css, '.footer-page', 'text-align', 'center');
        $this->assertDesignDeclaration($css, '.footer-page', 'align-self', 'center');
    }

    public function testModernDesignUsesSixMillimetreMarginsInEveryHtmlPageFormat(): void
    {
        foreach (['a4' => [210, 297], 'a5' => [148, 210]] as $page => [$width, $height]) {
            foreach (['portrait', 'landscape'] as $orientation) {
                $dom = $this->modernDesignDom($page, $orientation);
                $css = $this->designCss($dom);
                $this->assertDesignDeclaration($css, '@page', 'margin', '6mm');
                $this->assertDesignDeclaration($css, 'body.is-'.$orientation, 'margin', '0');
                $this->assertDesignDeclaration($css, 'body.is-'.$orientation, 'padding', '18px');
                $scripts = '';
                foreach ($dom->query('//script') as $script) {
                    $scripts .= $script->textContent;
                }
                self::assertMatchesRegularExpression('/const\s+widthMm\s*=\s*'.(($orientation === 'landscape' ? $height : $width) - 12).'\s*;/', $scripts, "$page $orientation : la largeur doit déduire deux marges de 6 mm.");
                self::assertMatchesRegularExpression('/const\s+heightMm\s*=\s*'.(($orientation === 'landscape' ? $width : $height) - 12).'\s*;/', $scripts, "$page $orientation : la pagination doit déduire deux marges de 6 mm.");
            }
        }
    }

    public function testMissingOrInvalidSettingKeepsClassicAndSeedIsClassic(): void
    {
        foreach ([null, 'invalid', 'modern'] as $value) {
            $this->service($value);
            self::assertSame($value === 'modern' ? 'modern' : 'classic', $this->support->settings()['invoice_template']);
        }
        $settings = array_column(SettingsCatalog::bootstrapSettings(), null, 'cle');
        self::assertSame('classic', $settings['IMPRESSION_INVOICE_TEMPLATE']['valeur']);
    }

    #[DataProvider('models')]
    public function testExportsIncludeNetTaxAndGross(string $template, bool $vat): void
    {
        $service = $this->service($template, $vat);
        foreach (['csv', 'excel', 'word'] as $format) {
            $response = $service->renderInvoice($this->id, $format, 'a4', 'portrait', 'attachment');
            ob_start();
            $response->sendContent();
            $content = ob_get_clean();
            if ($format === 'csv') {
                self::assertStringContainsString('Total HT', $content);
                self::assertStringContainsString('TOTAL TTC', $content);
                self::assertSame($vat, str_contains($content, 'TVA (18 %)'));
                self::assertStringContainsString($vat ? '1180' : '1000', $content);
            } else {
                $file = tempnam(sys_get_temp_dir(), 'invoice');
                file_put_contents($file, $content);
                $zip = new \ZipArchive();
                self::assertTrue($zip->open($file));
                $xml = $zip->getFromName($format === 'word' ? 'word/document.xml' : 'xl/sharedStrings.xml');
                self::assertStringContainsString('TOTAL TTC', $xml);
                self::assertStringContainsString('Total HT', $xml);
                self::assertSame($vat, str_contains($xml, 'TVA (18 %)'));
                $zip->close();
                unlink($file);
            }
        }
    }

    public static function paymentTermsModels(): iterable
    {
        foreach (['classic', 'modern'] as $template) {
            foreach (['html', 'pdf'] as $format) {
                yield "$template $format" => [$template, $format];
            }
        }
    }

    #[DataProvider('paymentTermsModels')]
    public function testPaymentTermsAreEscapedAndOrdered(string $template, string $format): void
    {
        $terms = ['Acompte <script>alert("test")</script> & solde', 'Puis paiement à 30 jours'];
        $service = $this->service($template, paymentTerms: $terms);
        $response = $service->renderInvoice($this->id, $format, 'a5', 'landscape', 'attachment');
        if ($format === 'pdf') {
            self::assertStringStartsWith('%PDF-', $response->getContent());
        }
        $html = $this->lastHtml;
        self::assertSame(1, substr_count($html, 'Conditions de paiement'));
        self::assertStringNotContainsString('<script>alert', $html);
        $escaped = htmlspecialchars($terms[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertStringContainsString($escaped, $html);
        self::assertLessThan(strpos($html, $terms[1]), strpos($html, $escaped));
        self::assertSame(2, substr_count($html, 'class="invoice-payment-term"'));
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            self::assertTrue($document->loadHTML('<?xml encoding="UTF-8">'.$html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $paymentClass = $template === 'modern' ? 'pay-info' : 'inv-payment-info';
        $items = $xpath->query('//div[@class="'.$paymentClass.'"]//ul[@class="invoice-payment-terms"]/li');
        self::assertSame(2, $items->length);
        foreach ($terms as $index => $term) {
            self::assertSame($term, $items->item($index)->textContent);
        }
    }

    #[DataProvider('paymentTermsModels')]
    public function testEmptyPaymentTermsAreHidden(string $template, string $format): void
    {
        $service = $this->service($template);
        $service->renderInvoice($this->id, $format, 'a4', 'portrait', 'attachment');
        self::assertStringNotContainsString('Conditions de paiement', $this->lastHtml);
        self::assertStringNotContainsString('class="invoice-payment-term"', $this->lastHtml);
    }

    public static function paymentTermsExports(): iterable
    {
        foreach (['csv', 'excel', 'word'] as $format) {
            foreach ([false, true] as $withTerms) {
                yield $format.($withTerms ? ' terms' : ' empty') => [$format, $withTerms];
            }
        }
    }

    #[DataProvider('paymentTermsExports')]
    public function testPaymentTermsExportsPreserveTextAndOrder(string $format, bool $withTerms): void
    {
        $terms = $withTerms ? ['Acompte <30%> & "accord"; confirmé', '=Solde à 30 jours'] : [];
        $service = $this->service(paymentTerms: $terms);
        $response = $service->renderInvoice($this->id, $format, 'a4', 'portrait', 'attachment');
        ob_start();
        try {
            $response->sendContent();
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if ($format === 'csv') {
            $stream = fopen('php://temp', 'w+');
            try {
                fwrite($stream, $content);
                rewind($stream);
                $texts = [];
                while (($row = fgetcsv($stream, 0, ';')) !== false) {
                    $texts[] = $row[0];
                }
            } finally {
                fclose($stream);
            }
        } else {
            $file = tempnam(sys_get_temp_dir(), 'invoice-terms');
            try {
                file_put_contents($file, $content);
                if ($format === 'excel') {
                    $spreadsheet = IOFactory::load($file);
                    try {
                        $sheet = $spreadsheet->getActiveSheet();
                        $texts = array_column($sheet->toArray(), 0);
                        if ($withTerms) {
                            $cell = $sheet->getCell('A'.$sheet->getHighestRow());
                            self::assertSame(DataType::TYPE_STRING, $cell->getDataType());
                        }
                    } finally {
                        $spreadsheet->disconnectWorksheets();
                    }
                } else {
                    $zip = new \ZipArchive();
                    self::assertTrue($zip->open($file));
                    try {
                        $xml = $zip->getFromName('word/document.xml');
                    } finally {
                        $zip->close();
                    }
                    $document = new \DOMDocument();
                    self::assertTrue($document->loadXML($xml));
                    $xpath = new \DOMXPath($document);
                    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $texts = [];
                    foreach ($xpath->query('//w:t') as $text) {
                        $texts[] = $text->textContent;
                    }
                }
            } finally {
                unlink($file);
            }
        }
        if ($withTerms) {
            self::assertSame(['Conditions de paiement', ...$terms], array_slice($texts, -3));
            self::assertSame(1, count(array_keys($texts, 'Conditions de paiement', true)));
        } else {
            self::assertNotContains('Conditions de paiement', $texts);
        }
    }

    public function testDedicatedMigrationIsIdempotentAndPreservesSelection(): void
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20261118130000.php';
        $connection = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE settings (cle VARCHAR(100) PRIMARY KEY, valeur TEXT NOT NULL, type VARCHAR(20), description TEXT, created_at DATETIME, updated_at DATETIME)');
        $connection->executeStatement('CREATE TABLE historique_settings (setting_cle VARCHAR(100))');
        $apply = static function (string $direction) use ($connection): void {
            $migration = new \DoctrineMigrations\Version20261118130000($connection, new \Psr\Log\NullLogger());
            $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
            }
        };
        $apply('up');
        self::assertSame('classic', $connection->fetchOne('SELECT valeur FROM settings'));
        self::assertSame('STRING', $connection->fetchOne('SELECT type FROM settings'));
        $connection->executeStatement("UPDATE settings SET valeur = 'modern'");
        $apply('up');
        self::assertSame('modern', $connection->fetchOne('SELECT valeur FROM settings'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM settings'));
        $connection->executeStatement("INSERT INTO historique_settings VALUES ('IMPRESSION_INVOICE_TEMPLATE')");
        $apply('down');
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM settings'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM historique_settings'));
        $connection->close();
    }

    public function testModernHtmlPaginationInRealBrowser(): void
    {
        $chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (!is_file($chrome)) {
            self::markTestSkipped('Chrome local non disponible pour le contrôle HTML.');
        }
        $service = $this->service('modern', true, 75, true, ['Acompte à la commande', 'Solde à réception']);
        $html = $service->renderInvoice($this->id, 'html', 'a5', 'landscape', 'attachment')->getContent();
        $probe = <<<'HTML'
<script>
window.addEventListener('load', () => {
    const sheets = Array.from(document.querySelectorAll('.invoice-sheet'));
    const rows = Array.from(document.querySelectorAll('.invoice-table tbody tr'));
    const overflow = sheets.some(s => s.scrollHeight > s.clientHeight + 1) || rows.some(r => r.getBoundingClientRect().bottom > r.closest('.invoice-sheet').querySelector('.invoice-footer').getBoundingClientRect().top + 1);
    document.body.setAttribute('data-pagination-overflow', String(overflow));
    document.body.setAttribute('data-pagination-pages', String(sheets.length));
    document.body.setAttribute('data-pagination-complete', String(sheets.some(s => s.textContent.includes('ARTICLE075')) && sheets.some(s => s.textContent.includes('FIN_DESCRIPTION')) && sheets.some(s => s.textContent.includes('TOTAL TTC')) && sheets.some(s => s.textContent.includes('Acompte à la commande')) && sheets.some(s => s.textContent.includes('Solde à réception'))));
});
</script>
HTML;
        $file = tempnam(sys_get_temp_dir(), 'invoice-browser');
                rename($file, $file.'.html');
                $file .= '.html';
        $profile = sys_get_temp_dir().'/invoice-chrome-'.bin2hex(random_bytes(6));
        file_put_contents($file, str_replace('</body>', $probe.'</body>', $html));
        $process = proc_open([$chrome, '--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--disable-extensions', '--user-data-dir='.$profile, '--dump-dom', 'file:///'.str_replace('\\', '/', $file)], [1 => ['pipe', 'w'], 2 => ['file', 'NUL', 'w']], $pipes);
        self::assertIsResource($process);
        $dom = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
        unlink($file);
        if (is_dir($profile)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($profile, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $entry) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
            @rmdir($profile);
        }
        self::assertStringContainsString('data-pagination-overflow="false"', $dom);
        self::assertStringContainsString('data-pagination-complete="true"', $dom);
        self::assertMatchesRegularExpression('/data-pagination-pages="([2-9]|[1-9][0-9]+)"/', $dom);
    }

    public static function pageFormats(): iterable
    {
        foreach (['classic', 'modern'] as $template) {
            foreach (['a4', 'a5'] as $page) {
                foreach (['portrait', 'landscape'] as $orientation) {
                    yield "$template $page $orientation" => [$template, $page, $orientation];
                }
            }
        }
    }

    #[DataProvider('pageFormats')]
    public function testRealPdfPaginationPreservesEveryArticleAndLongDescription(string $template, string $page, string $orientation): void
    {
        $service = $this->service($template, true, 45, true, ['Acompte à la commande', 'Solde à réception']);
        $response = $service->renderInvoice($this->id, 'pdf', $page, $orientation, 'attachment');
        self::assertStringStartsWith('%PDF-', $response->getContent());
        self::assertGreaterThan(1, preg_match_all('~/Type\s*/Page\b~', $response->getContent()));
        // Inspect actual Dompdf text frames rather than just asserting template strings.
        $dompdf = new Dompdf();
        $renderedText = '';
        $rowBottoms = [];
        $dompdf->setCallbacks([['event' => 'end_frame', 'f' => static function ($frame) use (&$renderedText, &$rowBottoms): void {
            $node = $frame->get_node();
            if ($node->nodeName === 'tr') {
                $table = $node->parentNode;
                while ($table !== null && $table->nodeName !== 'table') {
                    $table = $table->parentNode;
                }
                if ($table instanceof \DOMElement && in_array($table->getAttribute('class'), ['invoice-table', 'inv-table'], true)) {
                    $rowBottoms[] = $frame->get_position()['y'] + $frame->get_margin_height();
                }
            }
            if ($frame->get_node()->nodeName === '#text') {
                $renderedText .= $frame->get_node()->nodeValue."\n";
            }
        }]]);
        $dompdf->loadHtml($this->lastHtml);
        $dompdf->setPaper(strtoupper($page), $orientation);
        $dompdf->render();
        self::assertNotEmpty($rowBottoms);
        $contentBottom = $dompdf->getCanvas()->get_height() - ($template === 'modern' ? 6 + 32 : 10) * 72 / 25.4;
        foreach ($rowBottoms as $bottom) {
            self::assertLessThanOrEqual($contentBottom + 1, $bottom, 'Aucune ligne ne doit déborder dans la marge ou le pied de page.');
        }
        for ($i = 1; $i <= 45; ++$i) {
            self::assertStringContainsString('ARTICLE'.sprintf('%03d', $i), $renderedText);
        }
        self::assertStringContainsString('FIN_DESCRIPTION', $renderedText);
        self::assertStringContainsString('TOTAL TTC', $renderedText);
        self::assertStringContainsString('26 550', $renderedText);
        self::assertStringContainsString('Conditions de paiement', $renderedText);
        self::assertStringContainsString('Acompte à la commande', $renderedText);
        self::assertStringContainsString('Solde à réception', $renderedText);
    }
}
