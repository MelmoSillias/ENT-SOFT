<?php

namespace App\AccessAudit\Infrastructure\Persistence\Doctrine;

use App\AccessAudit\Domain\Entity\UtilisateurPermission;
use App\AccessAudit\Domain\Repository\UtilisateurPermissionRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<UtilisateurPermission>
 */
class DoctrineUtilisateurPermissionRepository extends ServiceEntityRepository implements UtilisateurPermissionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UtilisateurPermission::class);
    }

    public function findByUtilisateurId(Uuid $utilisateurId): array
    {
        $qb = $this->createQueryBuilder('up')
            ->andWhere('up.utilisateurId = :utilisateurId');
        UuidQueryParameter::bind($qb, 'utilisateurId', $utilisateurId);

        return $qb->getQuery()->getResult();
    }

    public function findOneByUtilisateurAndPermission(Uuid $utilisateurId, Uuid $permissionId): ?UtilisateurPermission
    {
        $qb = $this->createQueryBuilder('up')
            ->where('up.utilisateurId = :utilisateurId')
            ->andWhere('up.permission = :permissionId');
        UuidQueryParameter::bind($qb, 'utilisateurId', $utilisateurId);
        UuidQueryParameter::bind($qb, 'permissionId', $permissionId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function save(UtilisateurPermission $utilisateurPermission, bool $flush = true): void
    {
        $this->getEntityManager()->persist($utilisateurPermission);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UtilisateurPermission $utilisateurPermission, bool $flush = true): void
    {
        $this->getEntityManager()->remove($utilisateurPermission);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
