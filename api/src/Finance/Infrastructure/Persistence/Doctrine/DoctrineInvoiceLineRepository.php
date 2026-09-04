<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\InvoiceLine;
use App\Finance\Domain\Repository\InvoiceLineRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<InvoiceLine> */
class DoctrineInvoiceLineRepository extends ServiceEntityRepository implements InvoiceLineRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceLine::class);
    }

    public function save(InvoiceLine $line): void
    {
        $this->getEntityManager()->persist($line);
        $this->getEntityManager()->flush();
    }

    public function remove(InvoiceLine $line): void
    {
        $this->getEntityManager()->remove($line);
        $this->getEntityManager()->flush();
    }

    public function findByInvoiceId(Uuid $invoiceId): array
    {
        $qb = $this->createQueryBuilder('l')
            ->join('l.invoice', 'i')
            ->andWhere('i.id = :invoiceId');
        UuidQueryParameter::bind($qb, 'invoiceId', $invoiceId);

        return $qb->getQuery()->getResult();
    }
}
