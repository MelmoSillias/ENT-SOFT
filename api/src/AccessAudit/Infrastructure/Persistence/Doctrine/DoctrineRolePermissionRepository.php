<?php

namespace App\AccessAudit\Infrastructure\Persistence\Doctrine;

use App\AccessAudit\Domain\Entity\RolePermission;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use App\IdentityAccess\Domain\Enum\Role;
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

    public function findByRole(Role $role): array
    {
        return $this->findBy(['role' => $role]);
    }

    public function findPermissionCodesByRole(Role $role): array
    {
        $results = $this->createQueryBuilder('rp')
            ->select('p.code')
            ->join('rp.permission', 'p')
            ->where('rp.role = :role')
            ->andWhere('p.isEnabled = true')
            ->setParameter('role', $role)
            ->orderBy('p.code', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'code');
    }

    public function save(RolePermission $rolePermission, bool $flush = true): void
    {
        $this->getEntityManager()->persist($rolePermission);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
