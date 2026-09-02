<?php

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use Symfony\Component\Uid\Uuid;

interface UtilisateurRepositoryInterface
{
    public function save(Utilisateur $utilisateur): void;

    public function findById(Uuid $id): ?Utilisateur;

    public function findByLogin(string $login): ?Utilisateur;

    /** Premier compte inscrit (admin système). */
    public function findSystemAdmin(): ?Utilisateur;

    /** @return list<Utilisateur> */
    public function findAll(): array;
}
