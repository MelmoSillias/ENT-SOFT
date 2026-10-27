<?php

namespace App\Project\Application\Query\ActivityReport;

use App\Project\Application\Dto\ActivityReportResponseDto;
use App\Project\Domain\Enum\ActivityReportStatus;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ActivityReportQueryHandler
{
    public function __construct(private readonly ActivityReportRepositoryInterface $repository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(ActivityReportQuery $query): array
    {
        if ($query->status !== null && trim($query->status) !== '') {
            try {
                ActivityReportStatus::from($query->status);
            } catch (\ValueError) {
                throw new \InvalidArgumentException('Le statut du rapport est invalide.');
            }
        }

        return array_map(
            static fn ($report): array => ActivityReportResponseDto::fromEntity($report, false),
            $this->repository->findAllFiltered(
                $query->search,
                $this->date($query->from),
                $this->date($query->to),
                $query->status,
            ),
        );
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        try {
            $report = $this->repository->findById(Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            $report = null;
        }
        if ($report === null) {
            throw new \InvalidArgumentException('Rapport d’activité introuvable.');
        }

        return ActivityReportResponseDto::fromEntity($report);
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Le filtre de date doit respecter le format YYYY-MM-DD.');
        }

        return $date;
    }
}
