<?php

namespace App\Referentiel\Domain\Entity;

use App\Referentiel\Domain\Enum\ModeArrondi;
use App\Referentiel\Infrastructure\Persistence\Doctrine\DoctrineDeviseRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineDeviseRepository::class)]
#[ORM\Table(name: 'devises')]
#[ORM\UniqueConstraint(name: 'uniq_devise_code', fields: ['code'])]
class Devise
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 3)]
    private string $code;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $symbole;

    #[ORM\Column]
    private int $decimales = 2;

    #[ORM\Column(enumType: ModeArrondi::class)]
    private ModeArrondi $modeArrondi;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 6, nullable: true)]
    private ?string $uniteArrondi = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActif = true;

    public function __construct(
        string $nom,
        string $code,
        ModeArrondi $modeArrondi,
        int $decimales = 2,
        ?string $symbole = null,
        ?string $uniteArrondi = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->nom = $nom;
        $this->code = strtoupper($code);
        $this->modeArrondi = $modeArrondi;
        $this->decimales = $decimales;
        $this->symbole = $symbole;
        $this->uniteArrondi = $uniteArrondi;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSymbole(): ?string
    {
        return $this->symbole;
    }

    public function getDecimales(): int
    {
        return $this->decimales;
    }

    public function getModeArrondi(): ModeArrondi
    {
        return $this->modeArrondi;
    }

    public function getUniteArrondi(): ?string
    {
        return $this->uniteArrondi;
    }

    public function isActif(): bool
    {
        return $this->isActif;
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
        $this->touch();
    }

    public function setSymbole(?string $symbole): void
    {
        $this->symbole = $symbole;
        $this->touch();
    }

    public function setDecimales(int $decimales): void
    {
        $this->decimales = $decimales;
        $this->touch();
    }

    public function setModeArrondi(ModeArrondi $modeArrondi): void
    {
        $this->modeArrondi = $modeArrondi;
        $this->touch();
    }

    public function setUniteArrondi(?string $uniteArrondi): void
    {
        $this->uniteArrondi = $uniteArrondi;
        $this->touch();
    }

    public function setIsActif(bool $isActif): void
    {
        $this->isActif = $isActif;
        $this->touch();
    }
}
