<?php

namespace App\Employee\Domain\Repository;

use App\Employee\Domain\Entity\Employee;
use Symfony\Component\Uid\Uuid;

interface EmployeeRepositoryInterface
{
    public function save(Employee $employee): void;

    public function findById(Uuid $id): ?Employee;

    /**
     * @param list<Uuid> $ids
     * @return list<Employee>
     */
    public function findByIds(array $ids): array;

    /** @return list<Employee> */
    public function findAllEnabled(?string $search = null): array;
}
