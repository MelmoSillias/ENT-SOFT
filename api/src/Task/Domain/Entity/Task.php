<?php

namespace App\Task\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Infrastructure\Persistence\Doctrine\DoctrineTaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineTaskRepository::class)]
#[ORM\Table(name: 'tasks')]
class Task
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDue;

    #[ORM\Column(enumType: TaskStatus::class)]
    private TaskStatus $status;

    #[ORM\Column(type: 'uuid')]
    private Uuid $siteId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $employeeId;

    public function __construct(
        string $title,
        Uuid $siteId,
        TaskStatus $status = TaskStatus::PENDING,
        ?string $description = null,
        ?\DateTimeImmutable $dateDue = null,
        ?Uuid $employeeId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->title = $title;
        $this->siteId = $siteId;
        $this->status = $status;
        $this->description = $description;
        $this->dateCreation = new \DateTimeImmutable();
        $this->dateDue = $dateDue;
        $this->employeeId = $employeeId;
    }

    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }
    public function getDateDue(): ?\DateTimeImmutable { return $this->dateDue; }
    public function getStatus(): TaskStatus { return $this->status; }
    public function getSiteId(): Uuid { return $this->siteId; }
    public function getEmployeeId(): ?Uuid { return $this->employeeId; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setDateDue(?\DateTimeImmutable $dateDue): void { $this->dateDue = $dateDue; $this->touch(); }
    public function setStatus(TaskStatus $status): void { $this->status = $status; $this->touch(); }
    public function setSiteId(Uuid $siteId): void { $this->siteId = $siteId; $this->touch(); }
    public function setEmployeeId(?Uuid $employeeId): void { $this->employeeId = $employeeId; $this->touch(); }
}
