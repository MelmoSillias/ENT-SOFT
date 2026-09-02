<?php

namespace App\Employee\Application\Command\DeleteEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(DeleteEmployeeCommand $command): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($command->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($command->id);
        }

        $employee->disable();
        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}
