<?php

namespace App\Referentiel\Domain\Entity;

use App\Referentiel\Infrastructure\Persistence\Doctrine\DoctrineHistoriqueTauxRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineHistoriqueTauxRepository::class)]
#[ORM\Table(name: 'historique_taux')]
#[ORM\Index(name: 'idx_historique_taux_liaison', fields: ['liaisonId'])]
#[ORM\Index(name: 'idx_historique_taux_date', fields: ['dateModification'])]
class HistoriqueTaux
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $liaisonId;

    #[ORM\Column(length: 2)]
    private string $paysCode;

    #[ORM\Column(length: 100)]
    private string $paysNom;

    #[ORM\Column(length: 3)]
    private string $deviseCode;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $deviseSymbole;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 6, nullable: true)]
    private ?string $ancienTaux;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 6)]
    private string $nouveauTaux;

    #[ORM\Column(type: 'uuid')]
    private Uuid $utilisateurId;

    #[ORM\Column]
    private \DateTimeImmutable $dateModification;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $motif;

    public function __construct(
        Uuid $liaisonId,
        string $paysCode,
        string $paysNom,
        string $deviseCode,
        ?string $deviseSymbole,
        ?string $ancienTaux,
        string $nouveauTaux,
        Uuid $utilisateurId,
        ?string $motif = null,
    ) {
        $this->initializeUuid();
        $this->liaisonId = $liaisonId;
        $this->paysCode = $paysCode;
        $this->paysNom = $paysNom;
        $this->deviseCode = $deviseCode;
        $this->deviseSymbole = $deviseSymbole;
        $this->ancienTaux = $ancienTaux;
        $this->nouveauTaux = $nouveauTaux;
        $this->utilisateurId = $utilisateurId;
        $this->motif = $motif;
        $this->dateModification = new \DateTimeImmutable();
    }

    public static function fromLiaison(
        PaysDeviseLiaison $liaison,
        Uuid $utilisateurId,
        ?string $ancienTaux,
        ?string $motif = null,
    ): self {
        $pays = $liaison->getPays();
        $devise = $liaison->getDevise();

        return new self(
            liaisonId: $liaison->getId(),
            paysCode: $pays->getCode(),
            paysNom: $pays->getNom(),
            deviseCode: $devise->getCode(),
            deviseSymbole: $devise->getSymbole(),
            ancienTaux: $ancienTaux,
            nouveauTaux: $liaison->getTauxDefaut(),
            utilisateurId: $utilisateurId,
            motif: $motif,
        );
    }

    public function getLiaisonId(): Uuid
    {
        return $this->liaisonId;
    }

    public function getPaysCode(): string
    {
        return $this->paysCode;
    }

    public function getPaysNom(): string
    {
        return $this->paysNom;
    }

    public function getDeviseCode(): string
    {
        return $this->deviseCode;
    }

    public function getDeviseSymbole(): ?string
    {
        return $this->deviseSymbole;
    }

    public function getAncienTaux(): ?string
    {
        return $this->ancienTaux;
    }

    public function getNouveauTaux(): string
    {
        return $this->nouveauTaux;
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
