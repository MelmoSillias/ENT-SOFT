<?php

namespace App\Configuration\Infrastructure\Persistence\Doctrine;

use App\Configuration\Domain\Entity\HistoriqueSetting;
use App\Configuration\Domain\Repository\HistoriqueSettingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueSetting>
 */
class DoctrineHistoriqueSettingRepository extends ServiceEntityRepository implements HistoriqueSettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueSetting::class);
    }

    public function findBySettingCle(string $cle): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.settingCle = :cle')
            ->setParameter('cle', $cle)
            ->orderBy('h.dateModification', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(HistoriqueSetting $historiqueSetting, bool $flush = true): void
    {
        $this->getEntityManager()->persist($historiqueSetting);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
