<?php

namespace App\Impression\Application\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

final class TableImpressionService
{
    public function __construct(
        private readonly ImpressionDocumentSupport $support,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function render(string $tableType, array $payload, string $format, string $disposition = 'inline'): Response
    {
        $normalized = $this->normalizePayload($tableType, $payload);
        $page = (string) ($payload['page'] ?? 'a4');
        $orientation = (string) ($payload['orientation'] ?? 'portrait');
        $filename = $this->safeFilename($tableType.'-'.date('Y-m-d'));

        $html = $this->twig->render('impression/table.html.twig', [
            'data' => $normalized,
            'profile' => $this->support->profile(),
            'page' => $this->support->pageContext($page, $orientation),
            'auto_print' => $format === 'html' && $disposition === 'inline',
        ]);

        return match ($format) {
            'pdf' => $this->support->pdfResponse($html, $filename, $page, $orientation, $disposition),
            'csv' => $this->csvResponse($normalized, $filename),
            'excel' => $this->excelResponse($normalized, $filename),
            'word' => $this->wordResponse($normalized, $filename),
            default => $this->support->htmlResponse($html, $filename, $disposition),
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{title: string, filters_summary: string, columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, totals: list<array<string, mixed>>, results: list<array<string, mixed>>}
     */
    private function normalizePayload(string $tableType, array $payload): array
    {
        $columns = [];
        foreach ($payload['columns'] ?? [] as $col) {
            if (!is_array($col) || empty($col['key'])) {
                continue;
            }
            $align = (string) ($col['align'] ?? 'left');
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'left';
            }
            $type = (string) ($col['type'] ?? 'text');
            if (in_array($type, ['money', 'number', 'quantity'], true) && $align === 'left') {
                $align = 'right';
            }
            $columns[] = [
                'key' => (string) $col['key'],
                'label' => (string) ($col['label'] ?? $col['key']),
                'align' => $align,
                'type' => $type,
            ];
        }

        $rows = [];
        foreach ($payload['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalizedRow = [];
            foreach ($columns as $col) {
                $value = $row[$col['key']] ?? '';
                if (is_bool($value)) {
                    $value = $value ? 'Oui' : 'Non';
                } elseif (is_array($value)) {
                    $value = implode(', ', array_map(static fn ($v) => (string) $v, $value));
                }
                $normalizedRow[$col['key']] = (string) $value;
            }
            $rows[] = $normalizedRow;
        }

        $totals = [];
        foreach ($payload['totals'] ?? [] as $total) {
            if (!is_array($total)) {
                continue;
            }
            $align = (string) ($total['align'] ?? 'right');
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'right';
            }
            $label = (string) ($total['label'] ?? '');
            $value = (string) ($total['value'] ?? '');
            $targetKey = isset($total['key']) ? (string) $total['key'] : (isset($total['colspanKey']) ? (string) $total['colspanKey'] : null);

            $cells = [];
            $labelPlaced = false;
            $valuePlaced = false;
            $columnKeys = array_column($columns, 'key');
            $hasTarget = $targetKey !== null && in_array($targetKey, $columnKeys, true);

            foreach ($columns as $index => $col) {
                if ($hasTarget && $col['key'] === $targetKey) {
                    $cells[] = ['value' => $value, 'align' => $align, 'bold' => true];
                    $valuePlaced = true;
                    continue;
                }
                if (!$hasTarget && $index === count($columns) - 1 && count($columns) > 1) {
                    $cells[] = ['value' => $value, 'align' => $align, 'bold' => true];
                    $valuePlaced = true;
                    continue;
                }
                if (!$labelPlaced) {
                    $cells[] = ['value' => $label, 'align' => 'left', 'bold' => true];
                    $labelPlaced = true;
                } else {
                    $cells[] = ['value' => '', 'align' => 'left', 'bold' => false];
                }
            }
            if ($cells === [] && ($label !== '' || $value !== '')) {
                $cells[] = ['value' => trim($label.($value !== '' ? ' : '.$value : '')), 'align' => 'left', 'bold' => true];
                $valuePlaced = true;
            }
            if (!$valuePlaced && $value !== '' && $cells !== []) {
                $last = count($cells) - 1;
                if (($cells[$last]['value'] ?? '') === '') {
                    $cells[$last] = ['value' => $value, 'align' => $align, 'bold' => true];
                } else {
                    $cells[$last]['value'] = trim($cells[$last]['value'].' '.$value);
                }
            }

            $totals[] = [
                'label' => $label,
                'value' => $value,
                'align' => $align,
                'key' => $targetKey,
                'cells' => $cells,
            ];
        }

        $results = [];
        foreach ($payload['results'] ?? [] as $result) {
            if (!is_array($result)) {
                continue;
            }
            $results[] = [
                'label' => (string) ($result['label'] ?? ''),
                'value' => (string) ($result['value'] ?? ''),
            ];
        }

        if ($results === []) {
            $results[] = [
                'label' => 'Nombre de lignes',
                'value' => (string) count($rows),
            ];
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $title = 'Export '.$tableType;
        }

        return [
            'title' => $title,
            'filters_summary' => (string) ($payload['filters_summary'] ?? $payload['filtersSummary'] ?? ''),
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'results' => $results,
        ];
    }

    private function safeFilename(string $name): string
    {
        $safe = preg_replace('/[^\w.\-]+/', '-', $name) ?: 'export';

        return trim($safe, '-') ?: 'export';
    }

    /** @param array<string, mixed> $data */
    private function csvResponse(array $data, string $filename): StreamedResponse
    {
        $columns = $data['columns'];
        $rows = $data['rows'];
        $totals = $data['totals'];
        $results = $data['results'];

        return new StreamedResponse(function () use ($columns, $rows, $totals, $results) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, array_map(static fn (array $c) => $c['label'], $columns), ';');
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = $row[$col['key']] ?? '';
                }
                fputcsv($out, $line, ';');
            }
            if ($totals !== []) {
                fputcsv($out, [], ';');
                foreach ($totals as $total) {
                    fputcsv($out, [$total['label'], $total['value']], ';');
                }
            }
            if ($results !== []) {
                fputcsv($out, [], ';');
                foreach ($results as $result) {
                    fputcsv($out, [$result['label'], $result['value']], ';');
                }
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function excelResponse(array $data, string $filename): StreamedResponse
    {
        $columns = $data['columns'];
        $rows = $data['rows'];
        $totals = $data['totals'];
        $results = $data['results'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr((string) $data['title'], 0, 31) ?: 'Export');

        $colIndex = 1;
        foreach ($columns as $col) {
            $sheet->setCellValue([$colIndex, 1], $col['label']);
            $align = match ($col['align']) {
                'center' => Alignment::HORIZONTAL_CENTER,
                'right' => Alignment::HORIZONTAL_RIGHT,
                default => Alignment::HORIZONTAL_LEFT,
            };
            $sheet->getStyle([$colIndex, 1])->getFont()->setBold(true);
            $sheet->getStyle([$colIndex, 1])->getAlignment()->setHorizontal($align);
            ++$colIndex;
        }

        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($columns as $col) {
                $sheet->setCellValue([$colIndex, $rowIndex], $row[$col['key']] ?? '');
                $align = match ($col['align']) {
                    'center' => Alignment::HORIZONTAL_CENTER,
                    'right' => Alignment::HORIZONTAL_RIGHT,
                    default => Alignment::HORIZONTAL_LEFT,
                };
                $sheet->getStyle([$colIndex, $rowIndex])->getAlignment()->setHorizontal($align);
                ++$colIndex;
            }
            ++$rowIndex;
        }

        if ($totals !== []) {
            ++$rowIndex;
            foreach ($totals as $total) {
                $sheet->setCellValue([1, $rowIndex], $total['label']);
                $sheet->setCellValue([2, $rowIndex], $total['value']);
                $sheet->getStyle([1, $rowIndex])->getFont()->setBold(true);
                $sheet->getStyle([2, $rowIndex])->getFont()->setBold(true);
                $sheet->getStyle([2, $rowIndex])->getAlignment()->setHorizontal(
                    match ($total['align']) {
                        'center' => Alignment::HORIZONTAL_CENTER,
                        'left' => Alignment::HORIZONTAL_LEFT,
                        default => Alignment::HORIZONTAL_RIGHT,
                    }
                );
                ++$rowIndex;
            }
        }

        if ($results !== []) {
            ++$rowIndex;
            foreach ($results as $result) {
                $sheet->setCellValue([1, $rowIndex], $result['label']);
                $sheet->setCellValue([2, $rowIndex], $result['value']);
                $sheet->getStyle([1, $rowIndex])->getFont()->setBold(true);
                ++$rowIndex;
            }
        }

        foreach (range(1, max(1, count($columns))) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function wordResponse(array $data, string $filename): StreamedResponse
    {
        $columns = $data['columns'];
        $rows = $data['rows'];
        $totals = $data['totals'];
        $results = $data['results'];

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText((string) $data['title'], ['bold' => true, 'size' => 14]);
        if ($data['filters_summary'] !== '') {
            $section->addText((string) $data['filters_summary'], ['size' => 9, 'color' => '5b6b73']);
        }
        $section->addTextBreak(1);

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'c9d4d9',
            'cellMargin' => 50,
        ]);
        $table->addRow();
        $cellWidth = (int) max(1200, (int) floor(9000 / max(1, count($columns))));
        foreach ($columns as $col) {
            $cell = $table->addCell($cellWidth, ['bgColor' => '0f4c5c']);
            $cell->addText($col['label'], ['bold' => true, 'color' => 'FFFFFF', 'size' => 9], $this->wordAlign($col['align']));
        }
        foreach ($rows as $row) {
            $table->addRow();
            foreach ($columns as $col) {
                $cell = $table->addCell($cellWidth);
                $cell->addText((string) ($row[$col['key']] ?? ''), ['size' => 9], $this->wordAlign($col['align']));
            }
        }

        if ($totals !== []) {
            $section->addTextBreak(1);
            $section->addText('Totaux', ['bold' => true, 'size' => 11]);
            foreach ($totals as $total) {
                $section->addText($total['label'].' : '.$total['value'], ['size' => 10], $this->wordAlign($total['align']));
            }
        }

        if ($results !== []) {
            $section->addTextBreak(1);
            $section->addText('Résultats', ['bold' => true, 'size' => 11]);
            foreach ($results as $result) {
                $section->addText($result['label'].' : '.$result['value'], ['size' => 10]);
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

    /** @return array{alignment: string} */
    private function wordAlign(string $align): array
    {
        return [
            'alignment' => match ($align) {
                'center' => Jc::CENTER,
                'right' => Jc::END,
                default => Jc::START,
            },
        ];
    }
}
