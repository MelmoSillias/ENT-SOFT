<?php

namespace App\Prestataire\Domain\Enum;

enum PrestationPaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
}
