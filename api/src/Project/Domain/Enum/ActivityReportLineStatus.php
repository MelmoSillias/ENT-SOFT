<?php

namespace App\Project\Domain\Enum;

enum ActivityReportLineStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case BLOCKED = 'blocked';
}
