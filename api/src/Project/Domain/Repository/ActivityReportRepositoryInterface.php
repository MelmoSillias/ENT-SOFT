<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ActivityReport;
use Symfony\Component\Uid\Uuid;

interface ActivityReportRepositoryInterface
{
    public function save(ActivityReport $report): void;
    public function remove(ActivityReport $report): void;
    public function findById(Uuid $id): ?ActivityReport;
    public function codeExists(string $code): bool;

    /** @return list<ActivityReport> */
    public function findAllFiltered(
        ?string $search = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $status = null,
    ): array;
}
