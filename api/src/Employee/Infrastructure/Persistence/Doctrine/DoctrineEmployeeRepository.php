<?php

namespace App\Employee\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Employee> */
class DoctrineEmployeeRepository extends ServiceEntityRepository implements EmployeeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function save(Employee $employee): void
    {
        $this->getEntityManager()->persist($employee);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Employee
    {
        $qb = $this->createQueryBuilder('e');
        UuidQueryParameter::eq($qb, 'e.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('e');
        UuidQueryParameter::in($qb, 'e.id', 'ids', $ids);

        return $qb->getQuery()->getResult();
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('e.prenom LIKE :search OR e.nom LIKE :search OR e.email LIKE :search OR e.roleCode LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
