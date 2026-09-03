<?php

namespace App\Impression\Application\Service;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Configuration\Application\Service\AgenceLogoUploadService;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

final class InvoiceImpressionService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoiceAssembler $assembler,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly SettingRepositoryInterface $settingRepository,
        private readonly AgenceLogoUploadService $logoUploadService,
        private readonly Environment $twig,
    ) {
    }

    public function settings(): array
    {
        return [
            'default_page_table' => $this->setting('IMPRESSION_PAGE_TABLE', 'a4'),
            'default_orientation_table' => $this->setting('IMPRESSION_ORIENTATION_TABLE', 'portrait'),
            'default_page_invoice' => $this->setting('IMPRESSION_PAGE_INVOICE', 'a4'),
            'default_orientation_invoice' => $this->setting('IMPRESSION_ORIENTATION_INVOICE', 'portrait'),
            'default_export_format' => $this->setting('IMPRESSION_DEFAULT_EXPORT_FORMAT', 'pdf'),
            'margin_mm' => (int) $this->setting('IMPRESSION_MARGIN_MM', '10'),
            'footer_text' => $this->setting('IMPRESSION_FOOTER_TEXT', ''),
        ];
    }

    public function renderInvoice(string $id, string $format, string $page, string $orientation, string $disposition): Response
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($id);
        }

        $dto = $this->assembler->toDto($invoice)->toArray();
        $client = $this->clientRepository->findById($invoice->getClientId());
        $project = $invoice->getProjectId() ? $this->projectRepository->findById($invoice->getProjectId()) : null;

        $html = $this->twig->render('impression/facture.html.twig', [
            'data' => [
                'title' => 'Facture '.$invoice->getNumber(),
                'invoice' => $dto,
                'clientName' => $client?->getTitle() ?? '—',
                'projectName' => $project?->getTitle(),
                'statusLabel' => match ($invoice->getStatus()->value) {
                    'quote' => 'Devis',
                    'invoiced' => 'Facturé',
                    default => 'Brouillon',
                },
            ],
            'profile' => $this->profile(),
            'page' => $this->pageContext($page, $orientation),
            'auto_print' => $format === 'html' && $disposition === 'inline',
        ]);

        $filename = 'facture-'.$invoice->getNumber();

        return match ($format) {
            'pdf' => $this->pdfResponse($html, $filename, $page, $orientation, $disposition),
            'csv' => $this->csvResponse($dto, $filename),
            'excel' => $this->excelResponse($dto, $filename),
            'word' => $this->wordResponse($dto, $filename),
            default => new Response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => ($disposition === 'attachment' ? 'attachment' : 'inline').'; filename="'.$filename.'.html"',
            ]),
        };
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        $logo = $this->setting('AGENCE_LOGO_URL', '');
        $address = array_values(array_filter([
            $this->setting('AGENCE_ADRESSE', ''),
            $this->setting('AGENCE_VILLE', ''),
        ]));
        $phone = $this->setting('AGENCE_TELEPHONE', '');

        return [
            'shop_name' => $this->setting('AGENCE_NOM', 'ENT'),
            'show_logo' => in_array(strtolower($this->setting('IMPRESSION_SHOW_LOGO', 'true')), ['1', 'true', 'yes', 'on'], true),
            'logo_url' => $this->logoUploadService->resolveForDocuments($logo ?: null),
            'address_lines' => $address,
            'phones' => $phone !== '' ? [$phone] : [],
            'email' => $this->setting('AGENCE_EMAIL', ''),
            'website' => $this->setting('AGENCE_SITE_WEB', ''),
            'footer_text' => $this->setting('IMPRESSION_FOOTER_TEXT', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function pageContext(string $page, string $orientation): array
    {
        $sizes = [
            'a4' => ['portrait' => '210mm 297mm', 'landscape' => '297mm 210mm'],
            'a5' => ['portrait' => '148mm 210mm', 'landscape' => '210mm 148mm'],
        ];
        $format = $page === 'a5' ? 'a5' : 'a4';
        $orient = $orientation === 'landscape' ? 'landscape' : 'portrait';

        return [
            'format' => $format,
            'orientation' => $orient,
            'margin_mm' => (int) $this->setting('IMPRESSION_MARGIN_MM', '10'),
            'css_page_size' => $sizes[$format][$orient],
        ];
    }

    private function pdfResponse(string $html, string $filename, string $page, string $orientation, string $disposition): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($page === 'a5' ? 'A5' : 'A4', $orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        $contentDisposition = ($disposition === 'attachment' ? 'attachment' : 'inline').'; filename="'.$filename.'.pdf"';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $contentDisposition,
        ]);
    }

    /** @param array<string, mixed> $dto */
    private function csvResponse(array $dto, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($dto) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Description', 'Quantité', 'Prix unitaire', 'Montant'], ';');
            foreach ($dto['lines'] ?? [] as $line) {
                fputcsv($out, [
                    $line['description'] ?? '',
                    $line['quantity'] ?? '',
                    $line['unitPrice'] ?? '',
                    $line['amount'] ?? '',
                ], ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    /** @param array<string, mixed> $dto */
    private function excelResponse(array $dto, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Description', 'Quantité', 'Prix unitaire', 'Montant'], null, 'A1');
        $row = 2;
        foreach ($dto['lines'] ?? [] as $line) {
            $sheet->fromArray([
                $line['description'] ?? '',
                $line['quantity'] ?? '',
                $line['unitPrice'] ?? '',
                $line['amount'] ?? '',
            ], null, 'A'.$row);
            ++$row;
        }
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
    }

    /** @param array<string, mixed> $dto */
    private function wordResponse(array $dto, string $filename): StreamedResponse
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Facture '.$dto['number']);
        $table = $section->addTable();
        $table->addRow();
        foreach (['Description', 'Quantité', 'PU', 'Montant'] as $header) {
            $table->addCell(2000)->addText($header);
        }
        foreach ($dto['lines'] ?? [] as $line) {
            $table->addRow();
            $table->addCell(2000)->addText((string) ($line['description'] ?? ''));
            $table->addCell(2000)->addText((string) ($line['quantity'] ?? ''));
            $table->addCell(2000)->addText((string) ($line['unitPrice'] ?? ''));
            $table->addCell(2000)->addText((string) ($line['amount'] ?? ''));
        }
        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.docx"',
        ]);
    }

    private function setting(string $cle, string $default): string
    {
        return $this->settingRepository->findByCle($cle)?->getValeur() ?? $default;
    }
}
