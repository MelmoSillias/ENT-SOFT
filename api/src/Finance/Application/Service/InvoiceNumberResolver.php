<?php

namespace App\Finance\Application\Service;

use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Domain\Entity\Invoice;

final class InvoiceNumberResolver
{
    public const FORMAT_SEQUENTIAL = 'sequential';
    public const FORMAT_MONTHLY = 'monthly';

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
    }

    public function format(): string
    {
        $raw = strtolower(trim($this->settingRepository->findByCle('REFERENCE_INVOICE_FORMAT')?->getValeur() ?? self::FORMAT_SEQUENTIAL));

        return $raw === self::FORMAT_MONTHLY ? self::FORMAT_MONTHLY : self::FORMAT_SEQUENTIAL;
    }

    public function resolve(Invoice $invoice): string
    {
        if ($this->format() === self::FORMAT_MONTHLY) {
            return $invoice->getNumberMonthly();
        }

        return $invoice->getNumber();
    }
}
