<?php

namespace App\Employee\Application\Query\GetEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(GetEmployeeQuery $query): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($query->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($query->id);
        }

        return EmployeeResponseDto::fromEntity($employee);
    }
}
