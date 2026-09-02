<?php

namespace App\Configuration\Infrastructure\Persistence\Doctrine;

use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class DoctrineSettingRepository extends ServiceEntityRepository implements SettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findByCle(string $cle): ?Setting
    {
        return $this->find($cle);
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.cle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Setting $setting, bool $flush = true): void
    {
        $this->getEntityManager()->persist($setting);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
