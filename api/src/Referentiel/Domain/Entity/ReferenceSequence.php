<?php

namespace App\Referentiel\Domain\Entity;

use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reference_sequences')]
class ReferenceSequence
{
    #[ORM\Id]
    #[ORM\Column(length: 32, enumType: ReferenceSequenceType::class)]
    private ReferenceSequenceType $type;

    #[ORM\Column(type: 'integer')]
    private int $derniereValeur = 0;

    public function __construct(ReferenceSequenceType $type, int $derniereValeur = 0)
    {
        $this->type = $type;
        $this->derniereValeur = $derniereValeur;
    }

    public function getType(): ReferenceSequenceType
    {
        return $this->type;
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
