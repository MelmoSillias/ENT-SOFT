<?php

namespace App\Employee\Infrastructure\Persistence\Doctrine;

use App\Employee\Domain\Entity\EmployeePosition;
use App\Employee\Domain\Repository\EmployeePositionRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<EmployeePosition> */
class DoctrineEmployeePositionRepository extends ServiceEntityRepository implements EmployeePositionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeePosition::class);
    }

    public function save(EmployeePosition $position): void
    {
        $this->getEntityManager()->persist($position);
        $this->getEntityManager()->flush();
    }

    public function remove(EmployeePosition $position): void
    {
        $this->getEntityManager()->remove($position);
        $this->getEntityManager()->flush();
    }

    public function findByEmployeeId(Uuid $employeeId, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.recordedAt', 'DESC');
        UuidQueryParameter::eq($qb, 'p.employeeId', 'employeeId', $employeeId);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findAll(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.recordedAt', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestPerEmployee(?Uuid $employeeId = null): array
    {
        if (null !== $employeeId) {
            $rows = $this->findByEmployeeId($employeeId, 1);

            return $rows;
        }

        /** @var list<EmployeePosition> $all */
        $all = $this->createQueryBuilder('p')
            ->orderBy('p.recordedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($all as $position) {
            $key = $position->getEmployeeId()->toRfc4122();
            if (!isset($latest[$key])) {
                $latest[$key] = $position;
            }
        }

        return array_values($latest);
    }

    public function pruneOlderThan(Uuid $employeeId, int $keep = 10): void
    {
        $positions = $this->findByEmployeeId($employeeId);
        if (\count($positions) <= $keep) {
            return;
        }

        $em = $this->getEntityManager();
        foreach (\array_slice($positions, $keep) as $old) {
            $em->remove($old);
        }
        $em->flush();
    }
}
