<?php

namespace App\Project\Application\Command\ActivityReport;

use App\Project\Application\Dto\ActivityReportResponseDto;
use App\Project\Application\Service\ActivityReportFactory;
use App\Project\Domain\Entity\ActivityReport;
use App\Project\Domain\Enum\ActivityReportStatus;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ActivityReportCommandHandler
{
    public function __construct(
        private readonly ActivityReportRepositoryInterface $repository,
        private readonly ActivityReportFactory $factory,
    ) {
    }

    /** @return array<string, mixed> */
    public function save(ActivityReportCommand $command): array
    {
        $startDate = $this->date($command->startDate);
        $endDate = $this->date($command->endDate);
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('La date de début doit précéder ou être égale à la date de fin.');
        }

        try {
            $status = ActivityReportStatus::from($command->status);
        } catch (\ValueError) {
            throw new \InvalidArgumentException('Le statut du rapport est invalide.');
        }
        $description = $this->factory->description($command->description, $startDate, $endDate);

        if ($command->id === null) {
            $authorId = $command->authorId ? Uuid::fromString($command->authorId) : null;
            $report = new ActivityReport(
                code: $this->factory->generateCode($startDate),
                startDate: $startDate,
                endDate: $endDate,
                description: $description,
                status: $status,
                authorId: $authorId,
            );
        } else {
            $report = $this->get($command->id);
            $report->update($startDate, $endDate, $description, $status);
        }

        if ($command->lines !== null) {
            $report->replaceLines($this->factory->lines($report, $command->lines));
        }
        $this->repository->save($report);

        return ActivityReportResponseDto::fromEntity($report);
    }

    public function delete(string $id): void
    {
        $this->repository->remove($this->get($id));
    }

    private function get(string $id): ActivityReport
    {
        try {
            $report = $this->repository->findById(Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            $report = null;
        }
        if ($report === null) {
            throw new \InvalidArgumentException('Rapport d’activité introuvable.');
        }

        return $report;
    }

    private function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Les dates du rapport doivent respecter le format YYYY-MM-DD.');
        }

        return $date;
    }
}
