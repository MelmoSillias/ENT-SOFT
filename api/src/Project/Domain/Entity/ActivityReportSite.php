<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ActivityReportLineStatus;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'activity_report_sites')]
class ActivityReportSite
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(targetEntity: ActivityReport::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ActivityReport $report;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $projectId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $projectSiteId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $siteId;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $projectCodeSnapshot;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $projectTitleSnapshot;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $siteCodeSnapshot;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteTitleSnapshot;

    #[ORM\Column(enumType: ActivityReportLineStatus::class)]
    private ActivityReportLineStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $plannedTask;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $groupLabel;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $attendees;

    #[ORM\Column(type: 'integer')]
    private int $position;

    /** @param list<array<string, mixed>> $attendees */
    public function __construct(
        ActivityReport $report,
        ActivityReportLineStatus $status,
        int $position,
        ?Uuid $projectId = null,
        ?Uuid $projectSiteId = null,
        ?Uuid $siteId = null,
        ?string $projectCodeSnapshot = null,
        ?string $projectTitleSnapshot = null,
        ?string $siteCodeSnapshot = null,
        ?string $siteTitleSnapshot = null,
        ?string $observation = null,
        ?string $plannedTask = null,
        ?string $groupLabel = null,
        array $attendees = [],
    ) {
        $this->initializeUuid();
        $this->report = $report;
        $this->status = $status;
        $this->position = $position;
        $this->projectId = $projectId;
        $this->projectSiteId = $projectSiteId;
        $this->siteId = $siteId;
        $this->projectCodeSnapshot = self::nullable($projectCodeSnapshot);
        $this->projectTitleSnapshot = self::nullable($projectTitleSnapshot);
        $this->siteCodeSnapshot = self::nullable($siteCodeSnapshot);
        $this->siteTitleSnapshot = self::nullable($siteTitleSnapshot);
        $this->observation = self::nullable($observation);
        $this->plannedTask = self::nullable($plannedTask);
        $this->groupLabel = self::nullable($groupLabel);
        $this->attendees = $attendees;
    }

    private static function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }

    /** @param list<array<string, mixed>> $attendees */
    public function update(
        ActivityReportLineStatus $status,
        int $position,
        ?Uuid $projectId,
        ?Uuid $projectSiteId,
        ?Uuid $siteId,
        ?string $projectCodeSnapshot,
        ?string $projectTitleSnapshot,
        ?string $siteCodeSnapshot,
        ?string $siteTitleSnapshot,
        ?string $observation,
        ?string $plannedTask,
        ?string $groupLabel,
        array $attendees,
    ): void {
        $this->status = $status;
        $this->position = $position;
        $this->projectId = $projectId;
        $this->projectSiteId = $projectSiteId;
        $this->siteId = $siteId;
        $this->projectCodeSnapshot = self::nullable($projectCodeSnapshot);
        $this->projectTitleSnapshot = self::nullable($projectTitleSnapshot);
        $this->siteCodeSnapshot = self::nullable($siteCodeSnapshot);
        $this->siteTitleSnapshot = self::nullable($siteTitleSnapshot);
        $this->observation = self::nullable($observation);
        $this->plannedTask = self::nullable($plannedTask);
        $this->groupLabel = self::nullable($groupLabel);
        $this->attendees = $attendees;
    }

    public function getReport(): ActivityReport { return $this->report; }
    public function getProjectId(): ?Uuid { return $this->projectId; }
    public function getProjectSiteId(): ?Uuid { return $this->projectSiteId; }
    public function getSiteId(): ?Uuid { return $this->siteId; }
    public function getProjectCodeSnapshot(): ?string { return $this->projectCodeSnapshot; }
    public function getProjectTitleSnapshot(): ?string { return $this->projectTitleSnapshot; }
    public function getSiteCodeSnapshot(): ?string { return $this->siteCodeSnapshot; }
    public function getSiteTitleSnapshot(): ?string { return $this->siteTitleSnapshot; }
    public function getStatus(): ActivityReportLineStatus { return $this->status; }
    public function getObservation(): ?string { return $this->observation; }
    public function getPlannedTask(): ?string { return $this->plannedTask; }
    public function getGroupLabel(): ?string { return $this->groupLabel; }
    /** @return list<array<string, mixed>> */
    public function getAttendees(): array { return $this->attendees; }
    public function getPosition(): int { return $this->position; }
}
