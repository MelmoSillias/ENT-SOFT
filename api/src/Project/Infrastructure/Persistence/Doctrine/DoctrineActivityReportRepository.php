<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ActivityReport;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ActivityReport> */
final class DoctrineActivityReportRepository extends ServiceEntityRepository implements ActivityReportRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityReport::class);
    }

    public function save(ActivityReport $report): void
    {
        $this->getEntityManager()->persist($report);
        $this->getEntityManager()->flush();
    }

    public function remove(ActivityReport $report): void
    {
        $this->getEntityManager()->remove($report);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?ActivityReport
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.lines', 'l')
            ->addSelect('l');
        UuidQueryParameter::eq($qb, 'r.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function codeExists(string $code): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findAllFiltered(
        ?string $search = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $status = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.lines', 'l')
            ->addSelect('l')
            ->orderBy('r.startDate', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('r.code LIKE :search OR r.description LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }
        if ($from !== null) {
            $qb->andWhere('r.endDate >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('r.startDate <= :to')->setParameter('to', $to);
        }
        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('r.status = :status')->setParameter('status', trim($status));
        }

        return $qb->getQuery()->getResult();
    }
}
