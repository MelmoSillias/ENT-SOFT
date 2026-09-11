<?php

namespace App\Employee\Domain\Entity;

use App\Employee\Infrastructure\Persistence\Doctrine\DoctrineEmployeePositionRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineEmployeePositionRepository::class)]
#[ORM\Table(name: 'employee_positions')]
#[ORM\Index(name: 'idx_employee_positions_employee', columns: ['employee_id'])]
#[ORM\Index(name: 'idx_employee_positions_recorded', columns: ['recorded_at'])]
class EmployeePosition
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $employeeId;

    #[ORM\Column(type: 'float')]
    private float $latitude;

    #[ORM\Column(type: 'float')]
    private float $longitude;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $accuracy;

    public function __construct(
        Uuid $employeeId,
        float $latitude,
        float $longitude,
        ?float $accuracy = null,
        ?\DateTimeImmutable $recordedAt = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->employeeId = $employeeId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->accuracy = $accuracy;
        $this->recordedAt = $recordedAt ?? new \DateTimeImmutable();
    }

    public function getEmployeeId(): Uuid
    {
        return $this->employeeId;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }
}
