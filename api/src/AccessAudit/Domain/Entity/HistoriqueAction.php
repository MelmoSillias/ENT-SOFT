<?php

namespace App\AccessAudit\Domain\Entity;

use App\AccessAudit\Infrastructure\Persistence\Doctrine\DoctrineHistoriqueActionRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineHistoriqueActionRepository::class)]
#[ORM\Table(name: 'historique_actions')]
#[ORM\Index(name: 'idx_historique_action_utilisateur', fields: ['utilisateurId'])]
#[ORM\Index(name: 'idx_historique_action_date', fields: ['dateAction'])]
class HistoriqueAction
{
    use UuidEntityTrait;

    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'uuid')]
    private Uuid $utilisateurId;

    #[ORM\Column]
    private \DateTimeImmutable $dateAction;

    public function __construct(string $action, Uuid $utilisateurId, ?string $description = null)
    {
        $this->initializeUuid();
        $this->action = $action;
        $this->utilisateurId = $utilisateurId;
        $this->description = $description;
        $this->dateAction = new \DateTimeImmutable();
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUtilisateurId(): Uuid
    {
        return $this->utilisateurId;
    }

    public function getDateAction(): \DateTimeImmutable
    {
        return $this->dateAction;
    }
}
