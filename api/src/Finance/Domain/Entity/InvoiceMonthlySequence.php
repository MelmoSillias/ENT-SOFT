<?php

namespace App\Finance\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_monthly_sequences')]
class InvoiceMonthlySequence
{
    #[ORM\Id]
    #[ORM\Column(name: 'year_month', length: 7)]
    private string $yearMonth;

    #[ORM\Column(type: 'integer')]
    private int $derniereValeur = 0;

    public function __construct(string $yearMonth, int $derniereValeur = 0)
    {
        $this->yearMonth = $yearMonth;
        $this->derniereValeur = $derniereValeur;
    }

    public function getYearMonth(): string
    {
        return $this->yearMonth;
    }

    public function getDerniereValeur(): int
    {
        return $this->derniereValeur;
    }

    public function increment(): int
    {
        ++$this->derniereValeur;

        return $this->derniereValeur;
    }
}
