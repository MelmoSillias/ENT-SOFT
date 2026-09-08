<?php

namespace App\Site\Domain\Repository;

use App\Site\Domain\Entity\Site;
use Symfony\Component\Uid\Uuid;

interface SiteRepositoryInterface
{
    public function save(Site $site): void;

    public function findById(Uuid $id): ?Site;

    public function findByCode(string $code): ?Site;

    /** @return list<Site> */
    public function findAllEnabled(?string $search = null, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array;

    /** @return list<Site> */
    public function findByIds(array $ids): array;
}
