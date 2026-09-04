<?php

namespace App\Prestataire\Domain\Repository;

use App\Prestataire\Domain\Entity\Prestataire;
use Symfony\Component\Uid\Uuid;

interface PrestataireRepositoryInterface
{
    public function save(Prestataire $prestataire): void;

    public function findById(Uuid $id): ?Prestataire;

    /** @return list<Prestataire> */
    public function findAllEnabled(?string $search = null): array;
}
