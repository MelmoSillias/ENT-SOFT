<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\ActivityReport;
use App\Project\Domain\Entity\ActivityReportSite;

final class ActivityReportResponseDto
{
    /** @return array<string, mixed> */
    public static function fromEntity(ActivityReport $report, bool $withLines = true): array
    {
        $data = [
            'id' => (string) $report->getId(),
            'code' => $report->getCode(),
            'startDate' => $report->getStartDate()->format('Y-m-d'),
            'endDate' => $report->getEndDate()->format('Y-m-d'),
            'description' => $report->getDescription(),
            'status' => $report->getStatus()->value,
            'authorId' => $report->getAuthorId()?->toRfc4122(),
            'createdAt' => $report->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $report->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'lineCount' => $report->getLines()->count(),
        ];

        if ($withLines) {
            $data['lines'] = array_map(
                self::lineToArray(...),
                $report->getLines()->toArray(),
            );
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function lineToArray(ActivityReportSite $line): array
    {
        return [
            'id' => (string) $line->getId(),
            'projectId' => $line->getProjectId()?->toRfc4122(),
            'projectSiteId' => $line->getProjectSiteId()?->toRfc4122(),
            'siteId' => $line->getSiteId()?->toRfc4122(),
            'projectCode' => $line->getProjectCodeSnapshot(),
            'projectTitle' => $line->getProjectTitleSnapshot(),
            'siteCode' => $line->getSiteCodeSnapshot(),
            'siteTitle' => $line->getSiteTitleSnapshot(),
            'status' => $line->getStatus()->value,
            'observation' => $line->getObservation(),
            'plannedTask' => $line->getPlannedTask(),
            'groupLabel' => $line->getGroupLabel(),
            'attendees' => $line->getAttendees(),
            'position' => $line->getPosition(),
        ];
    }
}
