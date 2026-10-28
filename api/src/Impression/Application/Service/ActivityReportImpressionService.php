<?php

namespace App\Impression\Application\Service;

use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use App\Project\Domain\Entity\ActivityReport;
use App\Project\Domain\Entity\ActivityReportSite;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

final class ActivityReportImpressionService
{
    public function __construct(
        private readonly ActivityReportRepositoryInterface $reports,
        private readonly DocumentRepositoryInterface $documents,
        private readonly ImpressionDocumentSupport $support,
        private readonly Environment $twig,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function render(string $id, string $format, bool $bundle = false): Response
    {
        $report = $this->reports->findById(Uuid::fromString($id));
        if ($report === null) {
            throw new NotFoundHttpException('Rapport introuvable.');
        }

        $format = in_array($format, ['pdf', 'excel', 'word', 'html'], true) ? $format : 'pdf';
        $filename = $this->safeName($report->getCode() ?: 'rapport-activite');
        $content = $this->content($report, $format);

        if ($bundle) {
            return $this->zipResponse($report, $format, $filename, $content);
        }

        [$extension, $mime] = $this->formatMeta($format);

        return new Response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('attachment; filename="%s.%s"', $filename, $extension),
        ]);
    }

    private function content(ActivityReport $report, string $format): string
    {
        return match ($format) {
            'excel' => $this->excel($report),
            'word' => $this->word($report),
            'html' => $this->html($report),
            default => $this->pdf($report),
        };
    }

    private function html(ActivityReport $report): string
    {
        return $this->renderTemplate($report, true);
    }

    private function pdf(ActivityReport $report): string
    {
        $html = $this->renderTemplate($report, true);

        $response = $this->support->pdfResponse($html, 'rapport', 'a4', 'portrait', 'attachment');

        return (string) $response->getContent();
    }

    private function renderTemplate(ActivityReport $report, bool $embedImages): string
    {
        return $this->twig->render('impression/activity_report.html.twig', [
            'report' => $this->viewData($report, $embedImages),
            'profile' => $this->support->profile(),
            'page' => $this->support->pageContext('a4', 'portrait'),
            'render_mode' => 'pdf',
            'auto_print' => false,
        ]);
    }

    private function excel(ActivityReport $report): string
    {
        $groups = $this->groups($report, false);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport');
        $sheet->setCellValue('A1', $report->getCode());
        $sheet->setCellValue('A2', $report->getDescription());
        $sheet->mergeCells('A1:F1')->mergeCells('A2:F2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
        $headers = ['Site', 'Statut', 'Tâche prévue', 'Observation', "Chef d'équipe", 'Fichiers'];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:F4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');

        $row = 5;
        foreach ($groups as $group) {
            $sheet->setCellValue('A'.$row, $group['label']);
            $sheet->mergeCells('A'.$row.':F'.$row);
            $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true)->getColor()->setARGB('FF1E3A5F');
            $sheet->getStyle('A'.$row.':F'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F0FB');
            ++$row;
            foreach ($group['lines'] as $line) {
                $sheet->fromArray([
                    $line['siteLabel'],
                    $line['status'],
                    $line['plannedTask'],
                    $line['observation'],
                    $line['teamLeader'],
                    implode("\n", array_map(static fn (array $doc): string => $doc['title'] ?? '', $line['attachments'])),
                ], null, 'A'.$row);
                $sheet->getStyle('B'.$row.':F'.$row)->getAlignment()->setWrapText(true);
                ++$row;
            }
            ++$row;
        }
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        return $this->writerContent(new Xlsx($spreadsheet));
    }

    private function word(ActivityReport $report): string
    {
        $groups = $this->groups($report, true);
        $word = new PhpWord();
        $section = $word->addSection(['marginTop' => 900, 'marginBottom' => 900, 'marginLeft' => 900, 'marginRight' => 900]);
        $section->addTitle('Rapport d’activité', 1);
        $section->addText($report->getCode(), ['bold' => true, 'color' => '1E3A5F', 'size' => 13]);
        $section->addText($this->period($report));
        $section->addText($report->getDescription());

        foreach ($groups as $group) {
            $section->addTitle($group['label'], 2);
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => 'D8E0EC',
                'cellMargin' => 70,
                'width' => 100 * 50,
            ]);
            foreach (['Site', 'Statut', 'Tâche prévue', 'Observation', "Chef d'équipe", 'Fichiers'] as $header) {
                $table->addCell(1700)->addText($header, ['bold' => true, 'color' => '1E3A5F', 'size' => 9]);
            }
            foreach ($group['lines'] as $line) {
                $table->addRow();
                $table->addCell(1700)->addText($line['siteLabel'] ?: 'Site');
                $table->addCell(1100)->addText($line['status']);
                $table->addCell(1700)->addText($line['plannedTask'] ?: '—');
                $table->addCell(2200)->addText($line['observation'] ?: '—');
                $table->addCell(1300)->addText($line['teamLeader'] ?: '—');
                $filesCell = $table->addCell(2400);
                if ($line['attachments'] === []) {
                    $filesCell->addText('—');
                } else {
                    foreach ($line['attachments'] as $attachment) {
                        $filesCell->addText($attachment['title'] ?: 'Pièce jointe', ['size' => 8]);
                        if (!empty($attachment['description'])) {
                            $filesCell->addText($attachment['description'], ['italic' => true, 'size' => 8]);
                        }
                    }
                }
            }
            $section->addTextBreak(1);
        }

        return $this->writerContent(IOFactory::createWriter($word, 'Word2007'));
    }

    private function zipResponse(ActivityReport $report, string $format, string $filename, string $content): Response
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('L’extension ZIP est indisponible sur le serveur.');
        }
        [$extension] = $this->formatMeta($format);
        $temp = tempnam(sys_get_temp_dir(), 'activity-report-');
        $zip = new \ZipArchive();
        $zip->open($temp, \ZipArchive::OVERWRITE);
        $zip->addFromString($filename.'.'.$extension, $content);

        foreach ($report->getLines() as $index => $line) {
            $folder = sprintf(
                '%02d-%s/',
                $index + 1,
                $this->safeName(trim(($line->getSiteCodeSnapshot() ?? '').'-'.($line->getSiteTitleSnapshot() ?? 'site'))),
            );
            foreach ($this->documentsFor($line) as $document) {
                $path = $this->absolutePath($document->getFilePath());
                if ($path && is_file($path)) {
                    $zip->addFile($path, $folder.$this->safeName($document->getFileName()));
                }
            }
        }
        $zip->close();
        $body = file_get_contents($temp) ?: '';
        @unlink($temp);

        return new Response($body, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => sprintf('attachment; filename="%s-complet.zip"', $filename),
        ]);
    }

    /** @return array<string, mixed> */
    private function viewData(ActivityReport $report, bool $embedImages): array
    {
        $groups = $this->groups($report, $embedImages);
        $sites = [];
        foreach ($groups as $group) {
            foreach ($group['lines'] as $line) {
                $sites[] = $line;
            }
        }

        return [
            'code' => $report->getCode(),
            'period' => $this->period($report),
            'description' => $report->getDescription(),
            'status' => $report->getStatus()->value,
            'groups' => $groups,
            'sites' => $sites,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function groups(ActivityReport $report, bool $embedImages): array
    {
        $groups = [];
        foreach ($report->getLines() as $line) {
            $projectLabel = trim(($line->getProjectCodeSnapshot() ?? '').' '.($line->getProjectTitleSnapshot() ?? ''));
            $groupLabel = trim((string) ($line->getGroupLabel() ?? ''));
            $label = $projectLabel !== '' ? $projectLabel : ($groupLabel !== '' ? $groupLabel : 'Site hors projet');
            $groupKey = $line->getProjectId()?->toRfc4122() ?: ('manual-'.$label);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $label,
                    'project' => $projectLabel !== '' ? $projectLabel : null,
                    'lines' => [],
                ];
            }
            $groups[$groupKey]['lines'][] = $this->lineViewData($line, $embedImages);
        }

        return array_values($groups);
    }

    /** @return array<string, mixed> */
    private function lineViewData(ActivityReportSite $line, bool $embedImages): array
    {
        $attachments = [];
        foreach ($this->documentsFor($line) as $document) {
            $path = $this->absolutePath($document->getFilePath());
            $attachments[] = [
                'title' => $document->getTitle(),
                'description' => $document->getDescription(),
                'image' => $embedImages && $path && $this->isImage($path) ? $this->dataUri($path) : null,
            ];
        }

        $leader = $line->getAttendees()[0] ?? null;
        $leaderName = is_array($leader) ? (string) ($leader['name'] ?? 'Personne') : 'Personne';

        return [
            'project' => trim(($line->getProjectCodeSnapshot() ?? '').' '.($line->getProjectTitleSnapshot() ?? '')),
            'code' => $line->getSiteCodeSnapshot(),
            'title' => $line->getSiteTitleSnapshot() ?? 'Site',
            'siteLabel' => trim(trim((string) ($line->getSiteCodeSnapshot() ?? '')).' '.trim((string) ($line->getSiteTitleSnapshot() ?? ''))),
            'status' => $this->statusLabel($line->getStatus()->value),
            'statusKey' => $line->getStatus()->value,
            'plannedTask' => $line->getPlannedTask(),
            'observation' => $line->getObservation(),
            'groupLabel' => $line->getGroupLabel(),
            'teamLeader' => $leaderName,
            'attachments' => $attachments,
        ];
    }

    /** @return list<\App\Document\Domain\Entity\Document> */
    private function documentsFor(ActivityReportSite $line): array
    {
        return $this->documents->findByOwner(DocumentOwnerType::ACTIVITY_REPORT_SITE, $line->getId());
    }

    private function period(ActivityReport $report): string
    {
        $start = $report->getStartDate()->format('d/m/Y');
        $end = $report->getEndDate()->format('d/m/Y');

        return $start === $end ? 'Journée du '.$start : sprintf('Période du %s au %s', $start, $end);
    }

    private function absolutePath(string $filePath): ?string
    {
        $public = realpath($this->kernel->getProjectDir().'/public');
        if ($public === false) {
            return null;
        }
        $candidate = realpath($public.'/'.ltrim(str_replace('\\', '/', $filePath), '/'));

        return $candidate !== false && str_starts_with($candidate, $public.DIRECTORY_SEPARATOR) ? $candidate : null;
    }

    private function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    private function dataUri(string $path): string
    {
        $mime = mime_content_type($path) ?: 'image/jpeg';
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path) ?: '');
    }

    private function writerContent(object $writer): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'report-export-');
        $writer->save($temp);
        $content = file_get_contents($temp) ?: '';
        @unlink($temp);

        return $content;
    }

    /** @return array{string, string} */
    private function formatMeta(string $format): array
    {
        return match ($format) {
            'html' => ['html', 'text/html; charset=utf-8'],
            'excel' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'word' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            default => ['pdf', 'application/pdf'],
        };
    }

    private function safeName(string $value): string
    {
        $value = preg_replace('/[^\pL\pN._-]+/u', '-', trim($value)) ?: 'rapport';
        return trim($value, '-.');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'blocked' => 'Bloqué',
            default => 'En attente',
        };
    }
}
