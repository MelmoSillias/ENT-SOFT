<?php

namespace App\Project\Application\Service;

use App\Project\Domain\Entity\ActivityReport;
use App\Project\Domain\Entity\ActivityReportSite;
use App\Project\Domain\Enum\ActivityReportLineStatus;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ActivityReportFactory
{
    public function __construct(private readonly ActivityReportRepositoryInterface $repository)
    {
    }

    public function generateCode(\DateTimeImmutable $date): string
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = sprintf('RAP-%s-%s', $date->format('Ymd'), strtoupper(bin2hex(random_bytes(3))));
            if (!$this->repository->codeExists($code)) {
                return $code;
            }
        }

        return 'RAP-'.$date->format('Ymd').'-'.strtoupper(str_replace('-', '', Uuid::v7()->toRfc4122()));
    }

    public function description(
        ?string $description,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): string {
        $description = trim((string) $description);
        if ($description !== '') {
            return $description;
        }

        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('La date de début doit précéder ou être égale à la date de fin.');
        }

        $result = 'Rapport d’activité du '.$this->frenchDate($startDate);

        return $startDate == $endDate ? $result : $result.' au '.$this->frenchDate($endDate);
    }

    /**
     * Snapshots are accepted only through explicit create/update payloads and are never
     * refreshed from live project or site records.
     *
     * @param list<array<string, mixed>> $data
     * @return list<ActivityReportSite>
     */
    public function lines(ActivityReport $report, array $data): array
    {
        $lines = [];
        $existingById = [];
        foreach ($report->getLines() as $existing) {
            $existingById[(string) $existing->getId()] = $existing;
        }
        foreach (array_values($data) as $position => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Chaque ligne du rapport doit être un objet.');
            }
            $attendees = $item['attendees'] ?? [];
            if (!is_array($attendees) || !array_is_list($attendees)) {
                throw new \InvalidArgumentException('Les participants doivent être une liste de snapshots.');
            }
            foreach ($attendees as $attendee) {
                if (!is_array($attendee)) {
                    throw new \InvalidArgumentException('Chaque participant doit être un objet snapshot.');
                }
            }

            try {
                $status = ActivityReportLineStatus::from((string) ($item['status'] ?? 'pending'));
            } catch (\ValueError) {
                throw new \InvalidArgumentException(sprintf('Le statut de la ligne %d est invalide.', $position + 1));
            }

            $values = [
                'projectId' => $this->uuid($item['projectId'] ?? null),
                'projectSiteId' => $this->uuid($item['projectSiteId'] ?? null),
                'siteId' => $this->uuid($item['siteId'] ?? null),
                'projectCodeSnapshot' => $this->text($item['projectCode'] ?? $item['projectCodeSnapshot'] ?? null),
                'projectTitleSnapshot' => $this->text($item['projectTitle'] ?? $item['projectTitleSnapshot'] ?? null),
                'siteCodeSnapshot' => $this->text($item['siteCode'] ?? $item['siteCodeSnapshot'] ?? null),
                'siteTitleSnapshot' => $this->text($item['siteTitle'] ?? $item['siteTitleSnapshot'] ?? null),
                'observation' => $this->text($item['observation'] ?? null),
                'plannedTask' => $this->text($item['plannedTask'] ?? null),
                'groupLabel' => $this->text($item['groupLabel'] ?? null),
            ];
            $lineId = trim((string) ($item['id'] ?? ''));
            $line = $lineId !== '' ? ($existingById[$lineId] ?? null) : null;
            if ($lineId !== '' && $line === null) {
                throw new \InvalidArgumentException('Une ligne du rapport ne lui appartient pas.');
            }
            if ($line !== null) {
                $line->update(
                    status: $status,
                    position: $position,
                    projectId: $values['projectId'],
                    projectSiteId: $values['projectSiteId'],
                    siteId: $values['siteId'],
                    projectCodeSnapshot: $values['projectCodeSnapshot'],
                    projectTitleSnapshot: $values['projectTitleSnapshot'],
                    siteCodeSnapshot: $values['siteCodeSnapshot'],
                    siteTitleSnapshot: $values['siteTitleSnapshot'],
                    observation: $values['observation'],
                    plannedTask: $values['plannedTask'],
                    groupLabel: $values['groupLabel'],
                    attendees: $attendees,
                );
                $lines[] = $line;
                continue;
            }

            $lines[] = new ActivityReportSite(
                report: $report,
                status: $status,
                position: $position,
                projectId: $values['projectId'],
                projectSiteId: $values['projectSiteId'],
                siteId: $values['siteId'],
                projectCodeSnapshot: $values['projectCodeSnapshot'],
                projectTitleSnapshot: $values['projectTitleSnapshot'],
                siteCodeSnapshot: $values['siteCodeSnapshot'],
                siteTitleSnapshot: $values['siteTitleSnapshot'],
                observation: $values['observation'],
                plannedTask: $values['plannedTask'],
                groupLabel: $values['groupLabel'],
                attendees: $attendees,
            );
        }

        return $lines;
    }

    private function uuid(mixed $value): ?Uuid
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Uuid::fromString((string) $value);
    }

    private function text(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function frenchDate(\DateTimeImmutable $date): string
    {
        $months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return (int) $date->format('j').' '.$months[(int) $date->format('n')].' '.$date->format('Y');
    }
}
