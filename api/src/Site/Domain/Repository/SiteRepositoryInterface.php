<?php

namespace App\Site\Domain\Repository;

use App\Site\Domain\Entity\Site;
use Symfony\Component\Uid\Uuid;

interface SiteRepositoryInterface
{
    public function save(Site $site): void;

    public function findById(Uuid $id): ?Site;

    /** @return list<Site> */
    public function findAllEnabled(?string $search = null): array;

    /** @return list<Site> */
    public function findByIds(array $ids): array;
}
