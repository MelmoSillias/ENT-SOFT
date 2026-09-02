<?php

namespace App\AccessAudit\Domain\Repository;

use App\AccessAudit\Domain\Entity\HistoriqueAction;
use Symfony\Component\Uid\Uuid;

interface HistoriqueActionRepositoryInterface
{
    /**
     * @return list<HistoriqueAction>
     */
    public function search(
        ?Uuid $utilisateurId = null,
        ?string $action = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $page = 1,
        int $limit = 50,
        ?Uuid $excludeUtilisateurId = null,
    ): array;

    public function countSearch(
        ?Uuid $utilisateurId = null,
        ?string $action = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?Uuid $excludeUtilisateurId = null,
    ): int;

    public function save(HistoriqueAction $historiqueAction, bool $flush = true): void;
}
