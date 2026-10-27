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

        $format = in_array($format, ['pdf', 'excel', 'word'], true) ? $format : 'pdf';
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
            default => $this->pdf($report),
        };
    }

    private function pdf(ActivityReport $report): string
    {
        $html = $this->twig->render('impression/activity_report.html.twig', [
            'report' => $this->viewData($report, true),
            'profile' => $this->support->profile(),
            'page' => $this->support->pageContext('a4', 'portrait'),
            'auto_print' => false,
        ]);

        $response = $this->support->pdfResponse($html, 'rapport', 'a4', 'portrait', 'attachment');

        return (string) $response->getContent();
    }

    private function excel(ActivityReport $report): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport');
        $sheet->setCellValue('A1', $report->getCode());
        $sheet->setCellValue('A2', $report->getDescription());
        $sheet->mergeCells('A1:H1')->mergeCells('A2:H2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
        $headers = ['Projet', 'Code site', 'Site', 'Statut', 'Tâche prévue', 'Observation', 'Intervenants', 'Fichiers'];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:H4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');

        $row = 5;
        foreach ($report->getLines() as $line) {
            $docs = $this->documentsFor($line);
            $sheet->fromArray([
                trim(($line->getProjectCodeSnapshot() ?? '').' '.($line->getProjectTitleSnapshot() ?? '')),
                $line->getSiteCodeSnapshot(),
                $line->getSiteTitleSnapshot(),
                $this->statusLabel($line->getStatus()->value),
                $line->getPlannedTask(),
                $line->getObservation(),
                implode(', ', array_map(static fn (array $person): string => (string) ($person['name'] ?? 'Personne'), $line->getAttendees())),
                implode("\n", array_map(static fn ($doc): string => $doc->getFilePath(), $docs)),
            ], null, 'A'.$row);
            $sheet->getStyle('E'.$row.':H'.$row)->getAlignment()->setWrapText(true);
            ++$row;
        }
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        return $this->writerContent(new Xlsx($spreadsheet));
    }

    private function word(ActivityReport $report): string
    {
        $word = new PhpWord();
        $section = $word->addSection(['marginTop' => 900, 'marginBottom' => 900, 'marginLeft' => 900, 'marginRight' => 900]);
        $section->addTitle('Rapport d’activité', 1);
        $section->addText($report->getCode(), ['bold' => true, 'color' => '1E3A5F', 'size' => 13]);
        $section->addText($this->period($report));
        $section->addText($report->getDescription());

        foreach ($report->getLines() as $index => $line) {
            $section->addTitle(sprintf('%d. %s', $index + 1, $line->getSiteTitleSnapshot() ?? 'Site'), 2);
            $section->addText('Projet : '.(trim(($line->getProjectCodeSnapshot() ?? '').' '.($line->getProjectTitleSnapshot() ?? '')) ?: 'Hors projet'));
            $section->addText('Statut : '.$this->statusLabel($line->getStatus()->value), ['bold' => true]);
            if ($line->getPlannedTask()) {
                $section->addText('Tâche prévue : '.$line->getPlannedTask());
            }
            if ($line->getObservation()) {
                $section->addText('Observation : '.$line->getObservation());
            }
            $people = array_map(static fn (array $person): string => (string) ($person['name'] ?? 'Personne'), $line->getAttendees());
            if ($people !== []) {
                $section->addText('Présents : '.implode(', ', $people));
            }
            foreach ($this->documentsFor($line) as $document) {
                $path = $this->absolutePath($document->getFilePath());
                if ($path && $this->isImage($path)) {
                    $section->addImage($path, ['width' => 420, 'height' => 260, 'ratio' => true]);
                    $section->addText($document->getDescription() ?: $document->getTitle(), ['italic' => true, 'size' => 9]);
                } else {
                    $section->addText('Pièce jointe : '.$document->getTitle());
                }
            }
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
        $sites = [];
        foreach ($report->getLines() as $line) {
            $attachments = [];
            foreach ($this->documentsFor($line) as $document) {
                $path = $this->absolutePath($document->getFilePath());
                $attachments[] = [
                    'title' => $document->getTitle(),
                    'description' => $document->getDescription(),
                    'image' => $embedImages && $path && $this->isImage($path) ? $this->dataUri($path) : null,
                ];
            }
            $sites[] = [
                'project' => trim(($line->getProjectCodeSnapshot() ?? '').' '.($line->getProjectTitleSnapshot() ?? '')),
                'code' => $line->getSiteCodeSnapshot(),
                'title' => $line->getSiteTitleSnapshot() ?? 'Site',
                'status' => $this->statusLabel($line->getStatus()->value),
                'statusKey' => $line->getStatus()->value,
                'plannedTask' => $line->getPlannedTask(),
                'observation' => $line->getObservation(),
                'groupLabel' => $line->getGroupLabel(),
                'attendees' => array_map(static fn (array $person): string => (string) ($person['name'] ?? 'Personne'), $line->getAttendees()),
                'attachments' => $attachments,
            ];
        }

        return [
            'code' => $report->getCode(),
            'period' => $this->period($report),
            'description' => $report->getDescription(),
            'status' => $report->getStatus()->value,
            'sites' => $sites,
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
