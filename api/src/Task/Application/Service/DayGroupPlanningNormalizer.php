<?php

namespace App\Task\Application\Service;

use App\SharedKernel\Domain\Exception\DomainException;
use Symfony\Component\Uid\Uuid;

final class DayGroupPlanningNormalizer
{
    public function parseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException('Date invalide. Utilisez le format AAAA-MM-JJ.');
        }

        return $parsed;
    }

    /**
     * @param mixed $groups
     * @return list<array{id: string, label: ?string, leaderEmployeeId: ?string, memberIds: list<string>, sites: list<array{siteId: string, description: ?string}>}>
     */
    public function normalizeGroups(mixed $groups): array
    {
        if (!is_array($groups)) {
            throw new DomainException('Le planning doit contenir une liste de groupes.');
        }

        $seenEmployees = [];
        $seenSites = [];
        $normalized = [];

        foreach (array_values($groups) as $group) {
            if (!is_array($group)) {
                throw new DomainException('Un groupe du planning est invalide.');
            }

            $memberIds = [];
            $rawMembers = $group['memberIds'] ?? [];
            if (!is_array($rawMembers)) {
                throw new DomainException('La liste des membres d’un groupe est invalide.');
            }
            foreach ($rawMembers as $memberId) {
                if (!is_string($memberId) || $memberId === '' || isset($seenEmployees[$memberId])) {
                    continue;
                }
                $seenEmployees[$memberId] = true;
                $memberIds[] = $memberId;
            }

            $leader = $group['leaderEmployeeId'] ?? null;
            if (!is_string($leader) || !in_array($leader, $memberIds, true)) {
                $leader = null;
            }

            $sites = [];
            $rawSites = $group['sites'] ?? [];
            if (!is_array($rawSites)) {
                throw new DomainException('La liste des sites d’un groupe est invalide.');
            }
            foreach ($rawSites as $site) {
                if (!is_array($site)) {
                    continue;
                }
                $siteId = $site['siteId'] ?? null;
                if (!is_string($siteId) || $siteId === '' || isset($seenSites[$siteId])) {
                    continue;
                }
                $seenSites[$siteId] = true;
                $description = isset($site['description']) ? trim((string) $site['description']) : '';
                $sites[] = [
                    'siteId' => $siteId,
                    'description' => $description !== '' ? $description : null,
                ];
            }

            $label = isset($group['label']) ? trim((string) $group['label']) : '';
            $id = $group['id'] ?? null;
            if (!is_string($id) || $id === '') {
                $id = (string) Uuid::v7();
            }

            $normalized[] = [
                'id' => $id,
                'label' => $label !== '' ? $label : null,
                'leaderEmployeeId' => $leader,
                'memberIds' => $memberIds,
                'sites' => $sites,
            ];
        }

        return $normalized;
    }
}
