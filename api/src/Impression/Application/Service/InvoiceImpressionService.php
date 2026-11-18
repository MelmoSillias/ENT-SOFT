<?php

namespace App\Impression\Application\Service;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceNumberResolver;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
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
        private readonly ImpressionDocumentSupport $support,
        private readonly Environment $twig,
    ) {
    }

    public function settings(): array
    {
        return $this->support->settings();
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

        $pageContext = $this->support->pageContext($page, $orientation);
        $widthMm = $orientation === 'landscape' ? ($page === 'a5' ? 210 : 297) : ($page === 'a5' ? 148 : 210);
        $descriptionWidth = max(12, (int) (($widthMm - 2 * $pageContext['margin_mm']) * 0.37 * 3.78 / 7));
        $lines = [];
        foreach ($dto['lines'] ?? [] as $index => $line) {
            foreach ($this->descriptionFragments((string) ($line['description'] ?? ''), $descriptionWidth) as $part => $description) {
                $lines[] = [
                    'number' => $part === 0 ? $index + 1 : '↳',
                    'description' => $description,
                    'unit' => $part === 0 ? ($line['unit'] ?? 'Lot') : '',
                    'quantity' => $part === 0 ? $this->formatQuantity($line['quantity'] ?? 0) : '',
                    'unitPrice' => $part === 0 ? $this->formatAmount($line['unitPrice'] ?? 0) : '',
                    'amount' => $part === 0 ? $this->formatAmount($line['amount'] ?? 0) : '',
                ];
            }
        }

        $amountRaw = (float) ($dto['amount'] ?? 0);
        $paidAmountRaw = (float) ($dto['paidAmount'] ?? 0);
        $hasPayments = (bool) ($dto['hasPayments'] ?? false);
        $remainingAmountRaw = max(0.0, $amountRaw - $paidAmountRaw);
        $lastPaymentDate = null;
        if ($hasPayments) {
            $payments = $dto['payments'] ?? [];
            $lastPaymentRaw = $payments[0]['date'] ?? null;
            if (is_string($lastPaymentRaw) && $lastPaymentRaw !== '') {
                try {
                    $lastPaymentDate = (new \DateTimeImmutable($lastPaymentRaw))->format('d/m/Y');
                } catch (\Exception) {
                    $lastPaymentDate = $lastPaymentRaw;
                }
            }
        }
        $dateDisplay = $invoice->getDate()->format('d/m/Y');
        $numberDisplay = $this->numberResolver->resolve($invoice);
        $documentLabel = $this->documentLabel($invoice->getStatus());
        $documentShortLabel = $invoice->getStatus() === InvoiceStatus::INVOICED ? 'Facture' : 'Proforma';

        $serviceLines = array_values(array_filter([
            $client?->getAddress(),
            $this->formatPostalCity($client?->getPostalBox(), $client?->getCity()),
        ]));

        $template = $this->support->settings()['invoice_template'] === 'modern'
                    ? 'impression/facture_modern.html.twig'
                    : 'impression/facture.html.twig';
                $html = $this->twig->render($template, [
            'data' => [
                'title' => $documentLabel.' '.$numberDisplay,
                'documentLabel' => $documentLabel,
                'documentShortLabel' => $documentShortLabel,
                'invoice' => [
                    'number' => $numberDisplay,
                    'date' => $dateDisplay,
                    'amount' => $this->formatAmount($amountRaw),
                    'amountRaw' => $amountRaw,
                                        'vatEnabled' => (bool) ($dto['vatEnabled'] ?? false),
                                        'vatRate' => $this->formatQuantity($dto['vatRate'] ?? 18),
                                        'subtotal' => $this->formatAmount($dto['subtotal'] ?? $amountRaw),
                                        'vatAmount' => $this->formatAmount($dto['vatAmount'] ?? 0),
                    'lines' => $lines,
                                        'paymentTerms' => $dto['paymentTerms'] ?? [],
                ],
                'clientName' => $client?->getTitle() ?? '—',
                'serviceLines' => $serviceLines,
                'projectName' => $projectName,
                'amountInWords' => AmountInWordsFrench::format($amountRaw, documentLabel: $documentLabel),
                'paymentSummary' => [
                    'hasPayments' => $hasPayments,
                    'paidAmount' => $this->formatAmount($paidAmountRaw),
                    'remainingAmount' => $this->formatAmount($remainingAmountRaw),
                    'lastPaymentDate' => $lastPaymentDate,
                ],
            ],
            'profile' => $this->support->profile(),
            'page' => $this->support->pageContext($page, $orientation),
            'render_mode' => $format,
            'auto_print' => $format === 'html' && $disposition === 'inline',
        ]);

        $filenamePrefix = $invoice->getStatus() === InvoiceStatus::INVOICED ? 'facture' : 'facture-proforma';
        $filename = $filenamePrefix.'-'.preg_replace('/[^\w.\-]+/', '-', $numberDisplay);

        return match ($format) {
            'pdf' => $this->support->pdfResponse($html, $filename, $page, $orientation, $disposition, true),
            'csv' => $this->csvResponse($dto, $filename),
            'excel' => $this->excelResponse($dto, $filename),
            'word' => $this->wordResponse($dto, $filename, $documentLabel),
            default => $this->support->htmlResponse($html, $filename, $disposition),
        };
    }

    /**
     * Dompdf cannot split a table cell taller than a page. Bound description
     * fragments to six wrapped lines, keeping monetary values on the first only.
     * @return list<string>
     */
    private function descriptionFragments(string $description, int $width): array
    {
        $wrapped = [];
        foreach (preg_split('/\R/u', $description) ?: [''] as $paragraph) {
            $current = '';
            foreach (preg_split('/[\t ]+/u', $paragraph) ?: [] as $word) {
                foreach (mb_str_split($word, $width) as $piece) {
                    if ($current !== '' && mb_strlen($current.' '.$piece) > $width) {
                        $wrapped[] = $current;
                        $current = '';
                    }
                    $current .= ($current === '' ? '' : ' ').$piece;
                }
            }
            $wrapped[] = $current;
        }

        return array_map(static fn (array $lines): string => implode("\n", $lines), array_chunk($wrapped, 6));
    }

    /** @return list<array{string, float}> */
    private function totalRows(array $dto): array
    {
        $total = (float) ($dto['total'] ?? $dto['amount'] ?? 0);
        $rows = [['Total HT', (float) ($dto['subtotal'] ?? $total)]];
        if ($dto['vatEnabled'] ?? false) {
            $rows[] = ['TVA ('.$this->formatQuantity($dto['vatRate'] ?? 18).' %)', (float) ($dto['vatAmount'] ?? 0)];
        }
        $rows[] = ['TOTAL TTC', $total];

        return $rows;
    }

    private function documentLabel(InvoiceStatus $status): string
    {
        return $status === InvoiceStatus::INVOICED ? 'Facture' : 'Facture proforma';
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
            foreach ($this->totalRows($dto) as $total) {
                            fputcsv($out, [$total[0], '', '', '', $total[1]], ';');
                        }
            if ($dto['paymentTerms'] ?? []) {
                fputcsv($out, ['Conditions de paiement'], ';');
                foreach ($dto['paymentTerms'] as $term) {
                    fputcsv($out, [$term], ';');
                }
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
        foreach ($this->totalRows($dto) as $total) {
                    $sheet->fromArray([$total[0], '', '', '', $total[1]], null, 'A'.$row++);
                }
        if ($dto['paymentTerms'] ?? []) {
            $sheet->setCellValueExplicit('A'.$row++, 'Conditions de paiement', DataType::TYPE_STRING);
            foreach ($dto['paymentTerms'] as $term) {
                $sheet->setCellValueExplicit('A'.$row++, $term, DataType::TYPE_STRING);
            }
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
    private function wordResponse(array $dto, string $filename, string $documentLabel = 'Facture'): StreamedResponse
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText($documentLabel.' '.$dto['number']);
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
        foreach ($this->totalRows($dto) as [$label, $amount]) {
                    $section->addText($label.' : '.$this->formatAmount($amount).' FCFA');
                }
        if ($dto['paymentTerms'] ?? []) {
            $section->addText('Conditions de paiement');
            foreach ($dto['paymentTerms'] as $term) {
                $section->addText(htmlspecialchars($term, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
        }
        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.docx"',
        ]);
    }
}
