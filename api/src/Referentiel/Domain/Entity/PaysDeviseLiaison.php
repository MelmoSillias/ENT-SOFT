<?php

namespace App\Referentiel\Domain\Entity;

use App\Referentiel\Infrastructure\Persistence\Doctrine\DoctrinePaysDeviseLiaisonRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrinePaysDeviseLiaisonRepository::class)]
#[ORM\Table(name: 'pays_devise_liaisons')]
#[ORM\UniqueConstraint(name: 'uniq_pays_devise', fields: ['pays', 'devise'])]
class PaysDeviseLiaison
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Pays::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Pays $pays;

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Devise $devise;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 6)]
    private string $tauxDefaut;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefaut = false;

    public function __construct(Pays $pays, Devise $devise, string $tauxDefaut, bool $isDefaut = false)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->pays = $pays;
        $this->devise = $devise;
        $this->tauxDefaut = $tauxDefaut;
        $this->isDefaut = $isDefaut;
    }

    public function getPays(): Pays
    {
        return $this->pays;
    }

    public function getDevise(): Devise
    {
        return $this->devise;
    }

    public function getTauxDefaut(): string
    {
        return $this->tauxDefaut;
    }

    public function isDefaut(): bool
    {
        return $this->isDefaut;
    }

    public function setTauxDefaut(string $tauxDefaut): void
    {
        $this->tauxDefaut = $tauxDefaut;
        $this->touch();
    }

    public function setIsDefaut(bool $isDefaut): void
    {
        $this->isDefaut = $isDefaut;
        $this->touch();
    }

    public function setPays(Pays $pays): void
    {
        $this->pays = $pays;
        $this->touch();
    }

    public function setDevise(Devise $devise): void
    {
        $this->devise = $devise;
        $this->touch();
    }
}
