<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
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
        return $this->find($id);
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
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.clientId = :clientId')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('clientId', $clientId, 'uuid')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
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
