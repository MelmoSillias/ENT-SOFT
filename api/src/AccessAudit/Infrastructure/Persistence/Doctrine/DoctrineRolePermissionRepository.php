<?php

namespace App\AccessAudit\Infrastructure\Persistence\Doctrine;

use App\AccessAudit\Domain\Entity\RolePermission;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RolePermission>
 */
class DoctrineRolePermissionRepository extends ServiceEntityRepository implements RolePermissionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RolePermission::class);
    }

    public function findByRoleCode(string $roleCode): array
    {
        return $this->findBy(['roleCode' => strtoupper(trim($roleCode))]);
    }

    public function findPermissionCodesByRoleCode(string $roleCode): array
    {
        $results = $this->createQueryBuilder('rp')
            ->select('p.code')
            ->join('rp.permission', 'p')
            ->where('rp.roleCode = :role')
            ->andWhere('p.isEnabled = true')
            ->setParameter('role', strtoupper(trim($roleCode)))
            ->orderBy('p.code', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'code');
    }

    public function findAllGroupedByRole(): array
    {
        $rows = $this->createQueryBuilder('rp')
            ->select('rp.roleCode AS roleCode', 'p.code AS code')
            ->join('rp.permission', 'p')
            ->andWhere('p.isEnabled = true')
            ->orderBy('rp.roleCode', 'ASC')
            ->addOrderBy('p.code', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['roleCode']][] = $row['code'];
        }

        return $grouped;
    }

    public function save(RolePermission $rolePermission, bool $flush = true): void
    {
        $this->getEntityManager()->persist($rolePermission);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RolePermission $rolePermission, bool $flush = true): void
    {
        $this->getEntityManager()->remove($rolePermission);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
