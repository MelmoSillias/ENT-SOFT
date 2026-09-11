<?php

namespace App\Employee\Application\Query\ListEmployeePositions;

use App\Employee\Application\Dto\EmployeePositionResponseDto;
use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Entity\EmployeePosition;
use App\Employee\Domain\Repository\EmployeePositionRepositoryInterface;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListEmployeePositionsHandler
{
    public function __construct(
        private readonly EmployeePositionRepositoryInterface $positionRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(ListEmployeePositionsQuery $query): array
    {
        $employeeFilter = null !== $query->employeeId
            ? Uuid::fromString($query->employeeId)
            : null;

        if ($query->latestOnly) {
            $positions = $this->positionRepository->findLatestPerEmployee($employeeFilter);
        } elseif (null !== $employeeFilter) {
            $positions = $this->positionRepository->findByEmployeeId($employeeFilter, $query->limit);
        } else {
            $positions = $this->positionRepository->findAll($query->limit);
        }

        return $this->mapWithEmployees($positions);
    }

    /**
     * @param list<EmployeePosition> $positions
     * @return list<array<string, mixed>>
     */
    private function mapWithEmployees(array $positions): array
    {
        if ($positions === []) {
            return [];
        }

        $ids = array_map(
            static fn (EmployeePosition $p) => $p->getEmployeeId(),
            $positions,
        );
        $employees = $this->employeeRepository->findByIds($ids);
        /** @var array<string, Employee> $byId */
        $byId = [];
        foreach ($employees as $employee) {
            $byId[$employee->getId()->toRfc4122()] = $employee;
        }

        return array_map(
            static function (EmployeePosition $position) use ($byId) {
                $employee = $byId[$position->getEmployeeId()->toRfc4122()] ?? null;

                return EmployeePositionResponseDto::fromEntity($position, $employee)->toArray();
            },
            $positions,
        );
    }
}
