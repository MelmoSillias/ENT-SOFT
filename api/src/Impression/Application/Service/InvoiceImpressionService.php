<?php

namespace App\Impression\Application\Service;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceNumberResolver;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
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
        $documentLabel = $this->documentLabel($invoice->getStatus());
        $documentShortLabel = $invoice->getStatus() === InvoiceStatus::INVOICED ? 'Facture' : 'Proforma';

        $serviceLines = array_values(array_filter([
            $client?->getAddress(),
            $this->formatPostalCity($client?->getPostalBox(), $client?->getCity()),
        ]));

        $html = $this->twig->render('impression/facture.html.twig', [
            'data' => [
                'title' => $documentLabel.' '.$numberDisplay,
                'documentLabel' => $documentLabel,
                'documentShortLabel' => $documentShortLabel,
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
                'amountInWords' => AmountInWordsFrench::format($amountRaw, documentLabel: $documentLabel),
            ],
            'profile' => $this->support->profile(),
            'page' => $this->support->pageContext($page, $orientation),
            'auto_print' => $format === 'html' && $disposition === 'inline',
        ]);

        $filenamePrefix = $invoice->getStatus() === InvoiceStatus::INVOICED ? 'facture' : 'facture-proforma';
        $filename = $filenamePrefix.'-'.preg_replace('/[^\w.\-]+/', '-', $numberDisplay);

        return match ($format) {
            'pdf' => $this->support->pdfResponse($html, $filename, $page, $orientation, $disposition),
            'csv' => $this->csvResponse($dto, $filename),
            'excel' => $this->excelResponse($dto, $filename),
            'word' => $this->wordResponse($dto, $filename, $documentLabel),
            default => $this->support->htmlResponse($html, $filename, $disposition),
        };
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
        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.docx"',
        ]);
    }
}
