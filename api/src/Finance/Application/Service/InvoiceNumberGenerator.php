<?php

namespace App\Finance\Application\Service;

use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceMonthlySequenceRepositoryInterface;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\Referentiel\Domain\Repository\ReferenceSequenceRepositoryInterface;

final class InvoiceNumberGenerator
{
    private const MAX_SIGLE_LENGTH = 15;

    public function __construct(
        private readonly ReferenceSequenceRepositoryInterface $sequenceRepository,
        private readonly InvoiceMonthlySequenceRepositoryInterface $monthlySequenceRepository,
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
    }

    /**
     * @return array{sequential: string, monthly: string}
     */
    public function generate(\DateTimeImmutable $invoiceDate): array
    {
        $sequentialRank = $this->sequenceRepository->getAndIncrement(ReferenceSequenceType::INVOICE);
        $yearMonth = $invoiceDate->format('Y-m');
        $monthlyRank = $this->monthlySequenceRepository->getAndIncrement($yearMonth);
        $sigle = $this->sigle();

        $month = (int) $invoiceDate->format('n');
        $year = (int) $invoiceDate->format('Y');

        return [
            'sequential' => (string) $sequentialRank,
            'monthly' => sprintf('%s%d/%d-%d', $sigle, $monthlyRank, $month, $year),
        ];
    }

    private function sigle(): string
    {
        $value = trim($this->settingRepository->findByCle('REFERENCE_INVOICE_SIGLE')?->getValeur() ?? 'ENT');
        if ($value === '') {
            $value = 'ENT';
        }

        return mb_substr($value, 0, self::MAX_SIGLE_LENGTH);
    }
}
