<?php

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Utilisateur> */
class DoctrineUtilisateurRepository extends ServiceEntityRepository implements UtilisateurRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function save(Utilisateur $utilisateur): void
    {
        $this->getEntityManager()->persist($utilisateur);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Utilisateur
    {
        return $this->find($id);
    }

    public function findByLogin(string $login): ?Utilisateur
    {
        return $this->findOneBy(['login' => strtolower($login)]);
    }

    public function findSystemAdmin(): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.nom', 'ASC');

        $systemAdmin = $this->findSystemAdmin();
        if ($systemAdmin !== null) {
            $qb->andWhere('u.id != :systemAdminId')
                ->setParameter('systemAdminId', $systemAdmin->getId(), 'uuid');
        }

        return $qb->getQuery()->getResult();
    }
}
