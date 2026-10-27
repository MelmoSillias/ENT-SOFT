<?php

namespace App\Project\Application\Query\ActivityReport;

final readonly class ActivityReportQuery
{
    public function __construct(
        public ?string $id = null,
        public ?string $search = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $status = null,
    ) {
    }
}
