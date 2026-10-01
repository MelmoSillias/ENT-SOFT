<?php

namespace App\Task\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\PeriodQueryParameter;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Task\Domain\Entity\Task;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Task> */
class DoctrineTaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function save(Task $task): void
    {
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Task
    {
        $qb = $this->createQueryBuilder('t');
        UuidQueryParameter::eq($qb, 't.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findFiltered(
        ?Uuid $siteId = null,
        ?Uuid $employeeId = null,
        ?TaskStatus $status = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.dateDue', 'ASC');

        if ($siteId !== null) {
            UuidQueryParameter::eq($qb, 't.siteId', 'siteId', $siteId);
        }
        if ($employeeId !== null) {
            UuidQueryParameter::eq($qb, 't.employeeId', 'employeeId', $employeeId);
        }
        if ($status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        PeriodQueryParameter::applyDate($qb, 't.dateDue', $from, $to);

        return $qb->getQuery()->getResult();
    }

    public function countDueToday(): int
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.dateDue >= :today')
            ->andWhere('t.dateDue < :tomorrow')
            ->andWhere('t.status != :cancelled')
            ->setParameter('enabled', true)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->setParameter('tomorrow', $tomorrow, Types::DATE_IMMUTABLE)
            ->setParameter('cancelled', TaskStatus::CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
