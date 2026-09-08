<?php

namespace App\Upload\Domain\Repository;

use App\Upload\Domain\Entity\UploadSession;
use Symfony\Component\Uid\Uuid;

interface UploadSessionRepositoryInterface
{
    public function save(UploadSession $session): void;

    public function remove(UploadSession $session): void;

    public function findOwned(string $publicId, Uuid $ownerId): ?UploadSession;

    /** @return list<UploadSession> */
    public function findExpired(\DateTimeImmutable $now): array;
}
