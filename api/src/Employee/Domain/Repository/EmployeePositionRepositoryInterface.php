<?php

namespace App\Employee\Domain\Repository;

use App\Employee\Domain\Entity\EmployeePosition;
use Symfony\Component\Uid\Uuid;

interface EmployeePositionRepositoryInterface
{
    public function save(EmployeePosition $position): void;

    public function remove(EmployeePosition $position): void;

    /**
     * @return list<EmployeePosition>
     */
    public function findByEmployeeId(Uuid $employeeId, ?int $limit = null): array;

    /**
     * @return list<EmployeePosition>
     */
    public function findAll(?int $limit = null): array;

    /**
     * Latest position per employee (one row each).
     *
     * @return list<EmployeePosition>
     */
    public function findLatestPerEmployee(?Uuid $employeeId = null): array;

    /**
     * Keep only the $keep most recent positions for an employee; delete the rest.
     */
    public function pruneOlderThan(Uuid $employeeId, int $keep = 10): void;
}
