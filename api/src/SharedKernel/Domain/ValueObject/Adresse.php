<?php

namespace App\SharedKernel\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final class Adresse
{
    #[ORM\Column(nullable: true)]
    private ?string $ligne1 = null;

    #[ORM\Column(nullable: true)]
    private ?string $ligne2 = null;

    #[ORM\Column(nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $paysId = null;

    public function __construct(
        ?string $ligne1 = null,
        ?string $ligne2 = null,
        ?string $ville = null,
        ?string $codePostal = null,
        ?Uuid $paysId = null,
    ) {
        $this->ligne1 = $ligne1;
        $this->ligne2 = $ligne2;
        $this->ville = $ville;
        $this->codePostal = $codePostal;
        $this->paysId = $paysId;
    }

    public function getLigne1(): ?string
    {
        return $this->ligne1;
    }

    public function getLigne2(): ?string
    {
        return $this->ligne2;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function getPaysId(): ?Uuid
    {
        return $this->paysId;
    }

    public function isEmpty(): bool
    {
        return null === $this->ligne1
            && null === $this->ligne2
            && null === $this->ville
            && null === $this->codePostal
            && null === $this->paysId;
    }
}
