<?php

namespace App\Employee\Application\Query\ListEmployees;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;

final class ListEmployeesHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListEmployeesQuery $query): array
    {
        return array_map(
            static fn ($e) => EmployeeResponseDto::fromEntity($e)->toArray(),
            $this->employeeRepository->findAllEnabled($query->search),
        );
    }
}
