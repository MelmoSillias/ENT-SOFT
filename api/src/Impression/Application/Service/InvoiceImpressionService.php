<?php

namespace App\Impression\Application\Service;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Configuration\Application\Service\AgenceLogoUploadService;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceNumberResolver;
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
        private readonly InvoiceNumberResolver $numberResolver,
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
            'margin_mm' => (int) $this->setting('IMPRESSION_MARGIN_MM', '18'),
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
        $projectName = $invoice->getProjectLabel() ?: $project?->getTitle();

        $lines = [];
        foreach ($dto['lines'] ?? [] as $line) {
            $lines[] = [
                'description' => $line['description'] ?? '',
                'unit' => $line['unit'] ?? 'Lot',
                'quantity' => $this->formatQuantity($line['quantity'] ?? 0),
                'unitPrice' => $this->formatAmount($line['unitPrice'] ?? 0),
                'amount' => $this->formatAmount($line['amount'] ?? 0),
            ];
        }

        $amountRaw = (float) ($dto['amount'] ?? 0);
        $dateDisplay = $invoice->getDate()->format('d/m/Y');
        $numberDisplay = $this->numberResolver->resolve($invoice);

        $serviceLines = array_values(array_filter([
            $client?->getAddress(),
            $this->formatPostalCity($client?->getPostalBox(), $client?->getCity()),
        ]));

        $html = $this->twig->render('impression/facture.html.twig', [
            'data' => [
                'title' => 'Facture '.$numberDisplay,
                'invoice' => [
                    'number' => $numberDisplay,
                    'date' => $dateDisplay,
                    'amount' => $this->formatAmount($amountRaw),
                    'amountRaw' => $amountRaw,
                    'lines' => $lines,
                ],
                'clientName' => $client?->getTitle() ?? '—',
                'serviceLines' => $serviceLines,
                'projectName' => $projectName,
                'amountInWords' => AmountInWordsFrench::format($amountRaw),
            ],
            'profile' => $this->profile(),
            'page' => $this->pageContext($page, $orientation),
            'auto_print' => $format === 'html' && $disposition === 'inline',
        ]);

        $filename = 'facture-'.preg_replace('/[^\w.\-]+/', '-', $numberDisplay);

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
        $phone = $this->setting('AGENCE_TELEPHONE', '');
        $ville = $this->setting('AGENCE_VILLE', '');

        return [
            'shop_name' => $this->setting('AGENCE_NOM', 'ENT TECHNOLOGY'),
            'show_logo' => in_array(strtolower($this->setting('IMPRESSION_SHOW_LOGO', 'false')), ['1', 'true', 'yes', 'on'], true),
            'logo_url' => $this->logoUploadService->resolveForDocuments($logo ?: null),
            'address' => $this->setting('AGENCE_ADRESSE', ''),
            'ville' => $ville,
            'ville_bracket' => $ville !== '' ? '['.$ville.']' : '',
            'nina' => $this->setting('AGENCE_NINA', ''),
            'nif_fiscal' => $this->setting('AGENCE_NIF_FISCAL', ''),
            'phones' => $phone !== '' ? [$phone] : [],
            'phone' => $phone,
            'email' => $this->setting('AGENCE_EMAIL', ''),
            'website' => $this->setting('AGENCE_SITE_WEB', ''),
            'payee' => $this->setting('AGENCE_PAYEE', ''),
            'footer_text' => $this->setting('IMPRESSION_FOOTER_TEXT', ''),
            'address_lines' => array_values(array_filter([
                $this->setting('AGENCE_ADRESSE', ''),
                $ville,
            ])),
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
            'margin_mm' => (int) $this->setting('IMPRESSION_MARGIN_MM', '18'),
            'css_page_size' => $sizes[$format][$orient],
        ];
    }

    private function formatAmount(float|int|string $value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }

    private function formatQuantity(float|int|string $value): string
    {
        $f = (float) $value;
        if (abs($f - round($f)) < 0.00001) {
            return (string) (int) round($f);
        }

        return rtrim(rtrim(number_format($f, 2, ',', ' '), '0'), ',');
    }

    private function formatPostalCity(?string $postalBox, ?string $city): ?string
    {
        $parts = array_filter([$postalBox, $city]);
        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts).(str_ends_with(implode(' ', $parts), '-') ? '' : ' -');
    }

    private function pdfResponse(string $html, string $filename, string $page, string $orientation, string $disposition): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Serif');
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
            fputcsv($out, ['Description', 'Unit', 'Quantité', 'Prix unitaire', 'Montant'], ';');
            foreach ($dto['lines'] ?? [] as $line) {
                fputcsv($out, [
                    $line['description'] ?? '',
                    $line['unit'] ?? 'Lot',
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
        $sheet->fromArray(['Description', 'Unit', 'Quantité', 'Prix unitaire', 'Montant'], null, 'A1');
        $row = 2;
        foreach ($dto['lines'] ?? [] as $line) {
            $sheet->fromArray([
                $line['description'] ?? '',
                $line['unit'] ?? 'Lot',
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
        foreach (['Description', 'Unit', 'QTY', 'PU', 'Montant'] as $header) {
            $table->addCell(1800)->addText($header);
        }
        foreach ($dto['lines'] ?? [] as $line) {
            $table->addRow();
            $table->addCell(1800)->addText((string) ($line['description'] ?? ''));
            $table->addCell(1800)->addText((string) ($line['unit'] ?? 'Lot'));
            $table->addCell(1800)->addText((string) ($line['quantity'] ?? ''));
            $table->addCell(1800)->addText((string) ($line['unitPrice'] ?? ''));
            $table->addCell(1800)->addText((string) ($line['amount'] ?? ''));
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
