<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ProjectSiteStatus;
use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectSiteRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectSiteRepository::class)]
#[ORM\Table(name: 'project_sites')]
class ProjectSite
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $projectId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $siteId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $lotId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $technicianId;

    #[ORM\Column(enumType: ProjectSiteStatus::class)]
    private ProjectSiteStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $dateAdded;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $informationsValues = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $employeeIds = [];

    public function __construct(
        Uuid $projectId,
        Uuid $siteId,
        ProjectSiteStatus $status = ProjectSiteStatus::PENDING,
        array $informationsValues = [],
        array $employeeIds = [],
        ?Uuid $lotId = null,
        ?Uuid $technicianId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->projectId = $projectId;
        $this->siteId = $siteId;
        $this->status = $status;
        $this->dateAdded = new \DateTimeImmutable();
        $this->informationsValues = $informationsValues;
        $this->employeeIds = $employeeIds;
        $this->lotId = $lotId;
        $this->technicianId = $technicianId;
    }

    public function getProjectId(): Uuid { return $this->projectId; }
    public function getSiteId(): Uuid { return $this->siteId; }
    public function getLotId(): ?Uuid { return $this->lotId; }
    public function getTechnicianId(): ?Uuid { return $this->technicianId; }
    public function getStatus(): ProjectSiteStatus { return $this->status; }
    public function getDateAdded(): \DateTimeImmutable { return $this->dateAdded; }
    /** @return array<string, mixed> */
    public function getInformationsValues(): array { return $this->informationsValues; }
    /** @return list<string> */
    public function getEmployeeIds(): array { return $this->employeeIds; }

    public function setStatus(ProjectSiteStatus $status): void { $this->status = $status; $this->touch(); }
    public function setLotId(?Uuid $lotId): void { $this->lotId = $lotId; $this->touch(); }
    public function setTechnicianId(?Uuid $technicianId): void { $this->technicianId = $technicianId; $this->touch(); }
    /** @param array<string, mixed> $informationsValues */
    public function setInformationsValues(array $informationsValues): void { $this->informationsValues = $informationsValues; $this->touch(); }
    /** @param list<string> $employeeIds */
    public function setEmployeeIds(array $employeeIds): void { $this->employeeIds = $employeeIds; $this->touch(); }
}
