<?php

namespace App\Finance\Domain\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case QUOTE = 'quote';
    case INVOICED = 'invoiced';
}
