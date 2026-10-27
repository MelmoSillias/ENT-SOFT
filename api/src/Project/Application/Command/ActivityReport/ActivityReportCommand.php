<?php

namespace App\Project\Application\Command\ActivityReport;

final readonly class ActivityReportCommand
{
    /** @param list<array<string, mixed>>|null $lines */
    public function __construct(
        public ?string $id,
        public string $startDate,
        public string $endDate,
        public ?string $description,
        public string $status,
        public ?string $authorId,
        public ?array $lines,
    ) {
    }
}
