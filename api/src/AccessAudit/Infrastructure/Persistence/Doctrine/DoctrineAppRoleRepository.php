<?php

namespace App\AccessAudit\Infrastructure\Persistence\Doctrine;

use App\AccessAudit\Domain\Entity\AppRole;
use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<AppRole>
 */
class DoctrineAppRoleRepository extends ServiceEntityRepository implements AppRoleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppRole::class);
    }

    public function findById(Uuid $id): ?AppRole
    {
        return $this->find($id);
    }

    public function findByCode(string $code): ?AppRole
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    public function findAllOrdered(bool $enabledOnly = false): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.isSystem', 'DESC')
            ->addOrderBy('r.libelle', 'ASC');

        if ($enabledOnly) {
            $qb->andWhere('r.isEnabled = true');
        }

        return $qb->getQuery()->getResult();
    }

    public function save(AppRole $role, bool $flush = true): void
    {
        $this->getEntityManager()->persist($role);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
