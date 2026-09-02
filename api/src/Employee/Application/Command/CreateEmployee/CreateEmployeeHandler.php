<?php

namespace App\Employee\Application\Command\CreateEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(CreateEmployeeCommand $command): EmployeeResponseDto
    {
        $name = FieldValidator::requireNonEmpty($command->name, 'Nom');
        $email = FieldValidator::requireNonEmpty($command->email, 'Email');
        $phone = FieldValidator::requirePhone($command->phone);
        $function = FieldValidator::requireNonEmpty($command->function, 'Fonction');

        $employee = new Employee(
            name: $name,
            email: $email,
            phone: $phone,
            function: $function,
            address: $command->address,
            userId: $command->userId ? Uuid::fromString($command->userId) : null,
        );
        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}
