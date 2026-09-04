<?php

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
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
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
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
            $qb->andWhere('u.id != :systemAdminId');
            UuidQueryParameter::bind($qb, 'systemAdminId', $systemAdmin->getId());
        }

        return $qb->getQuery()->getResult();
    }
}
