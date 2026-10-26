<?php

namespace App\Task\Domain\Entity;

use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Task\Infrastructure\Persistence\Doctrine\DoctrineDayGroupPlanningRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineDayGroupPlanningRepository::class)]
#[ORM\Table(name: 'day_group_plannings')]
#[ORM\UniqueConstraint(name: 'uniq_day_group_planning_date', columns: ['plan_date'])]
class DayGroupPlanning
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $planDate;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    /** @param list<array<string, mixed>> $payload */
    public function __construct(\DateTimeImmutable $planDate, array $payload = [])
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->planDate = $planDate->setTime(0, 0);
        $this->payload = $payload;
    }

    public function getPlanDate(): \DateTimeImmutable
    {
        return $this->planDate;
    }

    /** @return list<array<string, mixed>> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param list<array<string, mixed>> $payload */
    public function replacePayload(array $payload): void
    {
        $this->payload = $payload;
        $this->touch();
    }
}
