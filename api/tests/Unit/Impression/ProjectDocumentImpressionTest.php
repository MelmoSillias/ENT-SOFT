<?php

namespace App\Tests\Unit\Impression;

use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Configuration\Application\Service\AgenceLogoUploadService;
use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Enum\TypeValeur;
use App\Configuration\Domain\Repository\HistoriqueSettingRepositoryInterface;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Client\Application\Service\ClientLogoUploadService;
use App\Impression\Application\Service\ImpressionDocumentSupport;
use App\Impression\Application\Service\ProjectDocumentImpressionService;
use App\Project\Application\Service\ProjectDocumentService;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectDocument;
use App\Project\Domain\Enum\ProjectDocumentType;
use App\Project\Domain\Repository\ProjectDocumentRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\SharedKernel\Application\Event\DomainEventDispatcherInterface;
use Dompdf\Dompdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ProjectDocumentImpressionTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=';
    private string $projectId;
    private string $id;
    private array $lastContext;
    private string $lastTemplate;
    private string $lastHtml;
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir().'/ent-delivery-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot.'/public/uploads/client-logos', 0777, true);
        file_put_contents($this->temporaryRoot.'/public/uploads/client-logos/00000000-0000-0000-0000-000000000000-00000000000000000000000000000000.png', base64_decode(self::PNG));
    }

    protected function tearDown(): void
    {
        unlink($this->temporaryRoot.'/public/uploads/client-logos/00000000-0000-0000-0000-000000000000-00000000000000000000000000000000.png');
        rmdir($this->temporaryRoot.'/public/uploads/client-logos');
        rmdir($this->temporaryRoot.'/public/uploads');
        rmdir($this->temporaryRoot.'/public');
        rmdir($this->temporaryRoot);
    }

    private function service(int $count = 2, bool $long = false, ?string $logoUrl = '/uploads/client-logos/00000000-0000-0000-0000-000000000000-00000000000000000000000000000000.png', ?string $object = null): ProjectDocumentImpressionService
    {
        $values = ['AGENCE_NOM' => 'Entreprise test', 'AGENCE_ADRESSE' => 'Adresse ENT', 'AGENCE_NINA' => 'NINA-TEST', 'AGENCE_LOGO_URL' => 'data:image/png;base64,'.self::PNG, 'IMPRESSION_INVOICE_TEMPLATE' => 'modern'];
        $settings = $this->createMock(SettingRepositoryInterface::class);
        $settings->method('findByCle')->willReturnCallback(static fn ($key) => isset($values[$key]) ? new Setting($key, $values[$key], TypeValeur::STRING) : null);
        $support = new ImpressionDocumentSupport($settings, new AgenceLogoUploadService($settings, $this->createMock(HistoriqueSettingRepositoryInterface::class), $this->createMock(DomainEventDispatcherInterface::class), $this->createMock(ValidatorInterface::class), dirname(__DIR__, 3)));
        $client = new Client('CLI1', 'Client test');
        $client->setLogoUrl($logoUrl);
        $project = new Project('PRJ1', 'Projet test', $client->getId());
        $lines = [];
        for ($i = 1; $i <= $count; ++$i) {
            $lines[] = ['description' => 'ARTICLE'.sprintf('%03d', $i).($long && $i === 1 ? ' '.str_repeat('Longue description ', 100).'FIN_DESCRIPTION' : ''), 'quantity' => 2.5];
        }
        $document = new ProjectDocument($project->getId(), ProjectDocumentType::DELIVERY_NOTE, 'BC-MANUEL-42', new \DateTimeImmutable('2026-11-20'), $client->getId(), $lines, $object);
        $this->id = (string) $document->getId();
        $this->projectId = (string) $project->getId();
        $documents = $this->createMock(ProjectDocumentRepositoryInterface::class);
        $documents->method('findById')->willReturn($document);
        $projects = $this->createMock(ProjectRepositoryInterface::class);
        $projects->method('findById')->willReturn($project);
        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->method('findById')->willReturn($client);

        $realTwig = new Environment(new FilesystemLoader(dirname(__DIR__, 3).'/templates'), ['strict_variables' => true]);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function ($template, $context) use ($realTwig) {
            $this->lastContext = $context;
            $this->lastTemplate = $template;
            return $this->lastHtml = $realTwig->render($template, $context);
        });
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->temporaryRoot);

        return new ProjectDocumentImpressionService(new ProjectDocumentService($documents, $projects, $clients), $clients, $support, $twig, new ClientLogoUploadService($clients, $this->createMock(ValidatorInterface::class), $kernel));
    }

    public static function templates(): iterable
    {
        yield 'classic' => ['classic'];
        yield 'modern' => ['modern'];
    }

    #[DataProvider('templates')]
    public function testOptionalObjectSpansAllColumnsAboveLineHeaders(string $template): void
    {
        foreach ([null, 'Installation & livraison <site>'] as $object) {
            $service = $this->service(object: $object);
            $response = $service->render($this->projectId, $this->id, template: $template, disposition: 'attachment');
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $cells = $xpath->query('//thead/tr/th[contains(@class,"delivery-object")]');
            self::assertSame($object === null ? 0 : 1, $cells->length);
            if ($object !== null) {
                $cell = $cells->item(0);
                self::assertSame('3', $cell->getAttribute('colspan'));
                self::assertSame($object, $cell->textContent);
                self::assertSame('ITEM', $xpath->query('following-sibling::tr[1]/th[1]', $cell->parentNode)->item(0)->textContent);
            }
        }
    }

    #[DataProvider('templates')]
    public function testHtmlUsesLogosManualNumberAndNoFinancialValues(string $template): void
    {
        $service = $this->service();
        $response = $service->render($this->projectId, $this->id, template: $template, disposition: 'attachment');
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $html = $response->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $text = $xpath->query('//body')->item(0)->textContent;
        foreach (['BON DE LIVRAISON', 'BC-MANUEL-42', '20/11/2026', 'Client test', 'ARTICLE001', '2,5', 'RAS'] as $value) {
            self::assertStringContainsString($value, $text);
        }
        foreach (['TOTAL TTC', 'Total HT', 'Prix unitaire', 'FCFA', 'Informations de règlement'] as $value) {
            self::assertStringNotContainsString($value, $text);
        }
        $signatures = $xpath->query('//table[contains(@class,"delivery-signatures")]//td');
                self::assertSame(2, $signatures->length);
                self::assertStringContainsString('Client test', $signatures->item(0)->textContent);
                self::assertStringContainsString($template === 'classic' ? 'ENT' : 'Entreprise test', $signatures->item(1)->textContent);
                self::assertSame(2, $xpath->query('//img[starts-with(@src,"data:image/png;base64,")]')->length);
        self::assertSame('data:image/png;base64,'.self::PNG, $this->lastContext['data']['clientLogo']);
        if ($template === 'classic') {
            self::assertSame(8, $xpath->query('//tr[contains(@class,"delivery-empty")]')->length);
            self::assertStringContainsString('width:57%', $html);
            self::assertStringContainsString('width:21%', $html);
            self::assertStringContainsString('width:22%', $html);
            self::assertStringContainsString('width:78%', $html);
            self::assertStringContainsString('BON DE COMMANDE N°', $text);
        } else {
            self::assertStringContainsString('brand-banner', $html);
            self::assertStringContainsString('beforeprint', $html);
            self::assertStringContainsString('Signature et cachet entreprise', $text);
        }
    }

    #[DataProvider('templates')]
    public function testMissingOrUnsafeDedicatedClientLogoDoesNotUseAttachments(string $template): void
    {
        foreach ([null, '/uploads/documents/logo.png', 'https://example.com/logo.png', '/uploads/client-logos/../../logo.png'] as $logoUrl) {
            $service = $this->service(logoUrl: $logoUrl);
            $response = $service->render($this->projectId, $this->id, template: $template);
            self::assertNull($this->lastContext['data']['clientLogo']);
            self::assertSame(1, substr_count($response->getContent(), 'src="data:image/png;base64,'));
        }
    }

    public function testTemplateDefaultsToInvoiceSettingAndInlineHtmlAutoPrints(): void
    {
        $service = $this->service();
        $service->render($this->projectId, $this->id);
        self::assertSame('impression/delivery_note_modern.html.twig', $this->lastTemplate);
        self::assertTrue($this->lastContext['auto_print']);
    }

    public static function pdfCases(): iterable
    {
        foreach (['classic', 'modern'] as $template) {
            yield $template.' single page' => [$template, 2, false];
            yield $template.' multiple pages' => [$template, 35, true];
        }
    }

    #[DataProvider('pdfCases')]
    public function testPdfIsGeneratedAndRetainsAllRowsAcrossPages(string $template, int $count, bool $long): void
    {
        $service = $this->service($count, $long);
        $response = $service->render($this->projectId, $this->id, 'pdf', 'a4', 'portrait', 'attachment', $template);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF-', $response->getContent());
        self::assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        self::assertFalse($this->lastContext['auto_print']);
        $lastArticle = 'ARTICLE'.sprintf('%03d', $count);
        self::assertStringContainsString($lastArticle, $this->lastHtml);
        $pdf = new Dompdf();
        $pdf->loadHtml($this->lastHtml);
        $pdf->setPaper('A4');
        // Collect text during rendering: Dompdf disposes of frames after rendering.
        $rendered = [];
        $geometry = [];
        $pdf->setCallbacks([['event' => 'begin_frame', 'f' => static function ($frame) use (&$rendered, &$geometry): void {
            if ($frame->get_node()->nodeName === 'table') {
                $geometry[] = [$frame->get_node()->getAttribute('class'), $frame->get_position(), $frame->get_margin_height()];
            }
            if ($frame->get_node()->nodeType === XML_TEXT_NODE) {
                $rendered[] = $frame->get_node()->nodeValue;
            }
        }]]);
        $pdf->render();
        if ($long) {
            self::assertGreaterThan(1, $pdf->getCanvas()->get_page_count());
        } else {
            self::assertSame(1, $pdf->getCanvas()->get_page_count(), json_encode($geometry));
        }
        $text = implode(' ', $rendered);
        self::assertStringContainsString($lastArticle, $text);
        if ($long) {
            self::assertStringContainsString('FIN_DESCRIPTION', $text);
        }
    }
}
