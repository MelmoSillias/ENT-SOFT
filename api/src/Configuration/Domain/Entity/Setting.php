<?php

namespace App\Configuration\Domain\Entity;

use App\Configuration\Domain\Enum\TypeValeur;
use App\Configuration\Domain\Event\SettingModifie;
use App\Configuration\Infrastructure\Persistence\Doctrine\DoctrineSettingRepository;
use App\SharedKernel\Domain\Event\RecordsDomainEvents;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineSettingRepository::class)]
#[ORM\Table(name: 'settings')]
class Setting
{
    use TimestampableTrait;
    use RecordsDomainEvents;

    #[ORM\Id]
    #[ORM\Column(length: 100)]
    private string $cle;

    #[ORM\Column(length: 500)]
    private string $valeur;

    #[ORM\Column(enumType: TypeValeur::class)]
    private TypeValeur $type;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description;

    public function __construct(string $cle, string $valeur, TypeValeur $type, ?string $description = null)
    {
        $this->cle = $cle;
        $this->valeur = $valeur;
        $this->type = $type;
        $this->description = $description;
        $this->initializeTimestamps();
    }

    public function getCle(): string
    {
        return $this->cle;
    }

    public function getValeur(): string
    {
        return $this->valeur;
    }

    public function getType(): TypeValeur
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function modifier(string $nouvelleValeur, Uuid $utilisateurId, ?string $motif = null): void
    {
        $ancienneValeur = $this->valeur;
        $this->valeur = $nouvelleValeur;
        $this->touch();
        $this->recordEvent(new SettingModifie($this->cle, $ancienneValeur, $nouvelleValeur, $utilisateurId, $motif));
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }
}
