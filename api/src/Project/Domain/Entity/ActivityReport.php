<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ActivityReportStatus;
use App\Project\Infrastructure\Persistence\Doctrine\DoctrineActivityReportRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineActivityReportRepository::class)]
#[ORM\Table(name: 'activity_reports')]
#[ORM\UniqueConstraint(name: 'uniq_activity_report_code', fields: ['code'])]
class ActivityReport
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(enumType: ActivityReportStatus::class)]
    private ActivityReportStatus $status;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $authorId;

    /** @var Collection<int, ActivityReportSite> */
    #[ORM\OneToMany(mappedBy: 'report', targetEntity: ActivityReportSite::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    public function __construct(
        string $code,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        string $description,
        ActivityReportStatus $status = ActivityReportStatus::DRAFT,
        ?Uuid $authorId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->description = $description;
        $this->status = $status;
        $this->authorId = $authorId;
        $this->lines = new ArrayCollection();
    }

    public function getCode(): string { return $this->code; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function getEndDate(): \DateTimeImmutable { return $this->endDate; }
    public function getDescription(): string { return $this->description; }
    public function getStatus(): ActivityReportStatus { return $this->status; }
    public function getAuthorId(): ?Uuid { return $this->authorId; }
    /** @return Collection<int, ActivityReportSite> */
    public function getLines(): Collection { return $this->lines; }

    public function update(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        string $description,
        ActivityReportStatus $status,
    ): void {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->description = $description;
        $this->status = $status;
        $this->touch();
    }

    /** @param list<ActivityReportSite> $lines */
    public function replaceLines(array $lines): void
    {
        $this->lines->clear();
        foreach ($lines as $line) {
            $this->lines->add($line);
        }
        $this->touch();
    }
}
