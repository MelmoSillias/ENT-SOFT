<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\UniqueConstraint(name: 'uniq_project_code', fields: ['code'])]
class Project
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $object;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDebut;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFin;

    #[ORM\Column(enumType: ProjectStatus::class)]
    private ProjectStatus $status;

    #[ORM\Column(type: 'float')]
    private float $budget;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $sitesInformations = [];

    public function __construct(
        string $code,
        string $title,
        Uuid $clientId,
        ProjectStatus $status = ProjectStatus::DRAFT,
        ?string $object = null,
        ?\DateTimeImmutable $dateDebut = null,
        ?\DateTimeImmutable $dateFin = null,
        float $budget = 0.0,
        array $sitesInformations = [],
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->clientId = $clientId;
        $this->status = $status;
        $this->object = $object;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->budget = $budget;
        $this->sitesInformations = $sitesInformations;
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getObject(): ?string { return $this->object; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function getStatus(): ProjectStatus { return $this->status; }
    public function getBudget(): float { return $this->budget; }
    public function getClientId(): Uuid { return $this->clientId; }
    /** @return list<array<string, mixed>> */
    public function getSitesInformations(): array { return $this->sitesInformations; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setObject(?string $object): void { $this->object = $object; $this->touch(); }
    public function setDateDebut(?\DateTimeImmutable $dateDebut): void { $this->dateDebut = $dateDebut; $this->touch(); }
    public function setDateFin(?\DateTimeImmutable $dateFin): void { $this->dateFin = $dateFin; $this->touch(); }
    public function setStatus(ProjectStatus $status): void { $this->status = $status; $this->touch(); }
    public function setBudget(float $budget): void { $this->budget = $budget; $this->touch(); }
    public function setClientId(Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
    /** @param list<array<string, mixed>> $sitesInformations */
    public function setSitesInformations(array $sitesInformations): void { $this->sitesInformations = $sitesInformations; $this->touch(); }
}
