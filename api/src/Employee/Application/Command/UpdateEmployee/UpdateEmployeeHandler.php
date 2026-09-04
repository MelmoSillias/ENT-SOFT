<?php

namespace App\Employee\Application\Command\UpdateEmployee;

use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
        private readonly AppRoleRepositoryInterface $appRoleRepository,
    ) {
    }

    public function handle(UpdateEmployeeCommand $command): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($command->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($command->id);
        }

        if ($command->prenom !== null) {
            $employee->setPrenom(FieldValidator::requireNonEmpty($command->prenom, 'Prénom'));
        }
        if ($command->nom !== null) {
            $employee->setNom(FieldValidator::requireNonEmpty($command->nom, 'Nom'));
        }
        if ($command->email !== null) {
            $employee->setEmail(FieldValidator::requireNonEmpty($command->email, 'Email'));
        }
        if ($command->phone !== null) {
            $employee->setPhone(FieldValidator::requirePhone($command->phone));
        }
        if ($command->roleCode !== null) {
            $roleCode = strtoupper(FieldValidator::requireNonEmpty($command->roleCode, 'Fonction'));
            $role = $this->appRoleRepository->findByCode($roleCode);
            if (null === $role || !$role->isEnabled()) {
                throw new \InvalidArgumentException('Fonction / rôle invalide ou masqué.');
            }
            $employee->setRoleCode($role->getCode());
            if (null !== $employee->getUserId()) {
                $user = $this->utilisateurRepository->findById($employee->getUserId());
                if (null !== $user) {
                    $user->setRoleCode($role->getCode());
                    if ($command->prenom !== null) {
                        $user->setPrenom($employee->getPrenom());
                    }
                    if ($command->nom !== null) {
                        $user->setNom($employee->getNom());
                    }
                    if ($command->phone !== null) {
                        $user->setTelephone($employee->getPhone());
                    }
                    $this->utilisateurRepository->save($user);
                }
            }
        }
        if ($command->hasAddress) {
            $employee->setAddress($command->address);
        }

        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}
