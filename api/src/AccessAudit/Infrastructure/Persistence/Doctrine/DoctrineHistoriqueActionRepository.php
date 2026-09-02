<?php

namespace App\AccessAudit\Infrastructure\Persistence\Doctrine;

use App\AccessAudit\Domain\Entity\HistoriqueAction;
use App\AccessAudit\Domain\Repository\HistoriqueActionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<HistoriqueAction>
 */
class DoctrineHistoriqueActionRepository extends ServiceEntityRepository implements HistoriqueActionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueAction::class);
    }

    public function search(
        ?Uuid $utilisateurId = null,
        ?string $action = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $page = 1,
        int $limit = 50,
        ?Uuid $excludeUtilisateurId = null,
    ): array {
        $qb = $this->createSearchQueryBuilder($utilisateurId, $action, $from, $to, $excludeUtilisateurId);

        return $qb
            ->orderBy('h.dateAction', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSearch(
        ?Uuid $utilisateurId = null,
        ?string $action = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?Uuid $excludeUtilisateurId = null,
    ): int {
        return (int) $this->createSearchQueryBuilder($utilisateurId, $action, $from, $to, $excludeUtilisateurId)
            ->select('COUNT(h.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(HistoriqueAction $historiqueAction, bool $flush = true): void
    {
        $this->getEntityManager()->persist($historiqueAction);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function createSearchQueryBuilder(
        ?Uuid $utilisateurId,
        ?string $action,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?Uuid $excludeUtilisateurId = null,
    ) {
        $qb = $this->createQueryBuilder('h');

        if (null !== $utilisateurId) {
            $qb->andWhere('h.utilisateurId = :utilisateurId')
                ->setParameter('utilisateurId', $utilisateurId, 'uuid');
        }

        if (null !== $excludeUtilisateurId) {
            $qb->andWhere('h.utilisateurId != :excludeUtilisateurId')
                ->setParameter('excludeUtilisateurId', $excludeUtilisateurId, 'uuid');
        }

        if (null !== $action && '' !== $action) {
            $qb->andWhere('h.action = :action')
                ->setParameter('action', $action);
        }

        if (null !== $from) {
            $qb->andWhere('h.dateAction >= :from')
                ->setParameter('from', $from);
        }

        if (null !== $to) {
            $qb->andWhere('h.dateAction <= :to')
                ->setParameter('to', $to);
        }

        return $qb;
    }
}
