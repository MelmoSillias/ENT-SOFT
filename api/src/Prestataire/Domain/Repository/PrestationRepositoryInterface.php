<?php

namespace App\Prestataire\Domain\Repository;

use App\Prestataire\Domain\Entity\Prestation;
use Symfony\Component\Uid\Uuid;

interface PrestationRepositoryInterface
{
    public function save(Prestation $prestation): void;

    public function findById(Uuid $id): ?Prestation;

    /** @return list<Prestation> */
    public function findAllEnabled(): array;

    /** @return list<Prestation> */
    public function findByPrestataireId(Uuid $prestataireId): array;
}
