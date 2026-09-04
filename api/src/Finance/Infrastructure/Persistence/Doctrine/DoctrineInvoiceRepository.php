<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Invoice> */
class DoctrineInvoiceRepository extends ServiceEntityRepository implements InvoiceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $invoice): void
    {
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Invoice
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('i.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByClientId(Uuid $clientId): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.clientId = :clientId')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('enabled', true);
        UuidQueryParameter::bind($qb, 'clientId', $clientId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countByStatus(InvoiceStatus $status): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('status', $status)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
