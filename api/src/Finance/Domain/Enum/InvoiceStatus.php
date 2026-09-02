<?php

namespace App\Finance\Domain\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
