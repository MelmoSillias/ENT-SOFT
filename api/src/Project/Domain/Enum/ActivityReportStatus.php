<?php

namespace App\Project\Domain\Enum;

enum ActivityReportStatus: string
{
    case DRAFT = 'draft';
    case FINALIZED = 'finalized';
}
