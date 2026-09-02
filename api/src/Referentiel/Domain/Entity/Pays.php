<?php

namespace App\Referentiel\Domain\Entity;

use App\Referentiel\Infrastructure\Persistence\Doctrine\DoctrinePaysRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrinePaysRepository::class)]
#[ORM\Table(name: 'pays')]
#[ORM\UniqueConstraint(name: 'uniq_pays_code', fields: ['code'])]
class Pays
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 2)]
    private string $code;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $indicatifTelephonique;

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Devise $devise;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActif = true;

    public function __construct(string $nom, string $code, Devise $devise, ?string $indicatifTelephonique = null)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->nom = $nom;
        $this->code = strtoupper($code);
        $this->devise = $devise;
        $this->indicatifTelephonique = $indicatifTelephonique;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getIndicatifTelephonique(): ?string
    {
        return $this->indicatifTelephonique;
    }

    public function getDevise(): Devise
    {
        return $this->devise;
    }

    public function getDeviseId(): \Symfony\Component\Uid\Uuid
    {
        return $this->devise->getId();
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

    public function setIndicatifTelephonique(?string $indicatifTelephonique): void
    {
        $this->indicatifTelephonique = $indicatifTelephonique;
        $this->touch();
    }

    public function setDevise(Devise $devise): void
    {
        $this->devise = $devise;
        $this->touch();
    }

    public function setIsActif(bool $isActif): void
    {
        $this->isActif = $isActif;
        $this->touch();
    }
}
