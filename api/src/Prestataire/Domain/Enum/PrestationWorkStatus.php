<?php

namespace App\Prestataire\Domain\Enum;

enum PrestationWorkStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
