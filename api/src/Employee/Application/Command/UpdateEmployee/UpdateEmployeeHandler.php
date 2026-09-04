<?php

namespace App\Employee\Application\Command\UpdateEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(UpdateEmployeeCommand $command): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($command->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($command->id);
        }

        if ($command->name !== null) {
            $employee->setName(FieldValidator::requireNonEmpty($command->name, 'Nom'));
        }
        if ($command->email !== null) {
            $employee->setEmail(FieldValidator::requireNonEmpty($command->email, 'Email'));
        }
        if ($command->phone !== null) {
            $employee->setPhone(FieldValidator::requirePhone($command->phone));
        }
        if ($command->function !== null) {
            $employee->setFunction(FieldValidator::requireNonEmpty($command->function, 'Fonction'));
        }
        if ($command->hasAddress) {
            $employee->setAddress($command->address);
        }
        if ($command->hasUserId) {
            $employee->setUserId($command->userId !== null && $command->userId !== ''
                ? Uuid::fromString($command->userId)
                : null);
        }

        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}
