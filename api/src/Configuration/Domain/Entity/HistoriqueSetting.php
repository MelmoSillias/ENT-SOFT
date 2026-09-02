<?php

namespace App\Configuration\Domain\Entity;

use App\Configuration\Infrastructure\Persistence\Doctrine\DoctrineHistoriqueSettingRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineHistoriqueSettingRepository::class)]
#[ORM\Table(name: 'historique_settings')]
#[ORM\Index(name: 'idx_historique_setting_cle', fields: ['settingCle'])]
class HistoriqueSetting
{
    use UuidEntityTrait;

    #[ORM\Column(length: 100)]
    private string $settingCle;

    #[ORM\Column(length: 500)]
    private string $ancienneValeur;

    #[ORM\Column(length: 500)]
    private string $nouvelleValeur;

    #[ORM\Column(type: 'uuid')]
    private Uuid $utilisateurId;

    #[ORM\Column]
    private \DateTimeImmutable $dateModification;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $motif;

    public function __construct(
        string $settingCle,
        string $ancienneValeur,
        string $nouvelleValeur,
        Uuid $utilisateurId,
        ?string $motif = null,
    ) {
        $this->initializeUuid();
        $this->settingCle = $settingCle;
        $this->ancienneValeur = $ancienneValeur;
        $this->nouvelleValeur = $nouvelleValeur;
        $this->utilisateurId = $utilisateurId;
        $this->motif = $motif;
        $this->dateModification = new \DateTimeImmutable();
    }

    public function getSettingCle(): string
    {
        return $this->settingCle;
    }

    public function getAncienneValeur(): string
    {
        return $this->ancienneValeur;
    }

    public function getNouvelleValeur(): string
    {
        return $this->nouvelleValeur;
    }

    public function getUtilisateurId(): Uuid
    {
        return $this->utilisateurId;
    }

    public function getDateModification(): \DateTimeImmutable
    {
        return $this->dateModification;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }
}
