<?php

namespace App\Document\Domain\Enum;

enum DocumentOwnerType: string
{
    case CLIENT = 'client';
    case PROJECT = 'project';
    case SITE = 'site';
    case ACTIVITY_REPORT_SITE = 'activity_report_site';
    case FINANCIAL_TRANSACTION = 'financial_transaction';
}
