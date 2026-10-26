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

    /**
     * @param mixed $payload
     * @return array{groups: list<array<string, mixed>>, withheldEmployeeIds: list<string>, withheldSiteIds: list<string>}
     */
    public function readStored(mixed $payload): array
    {
        if (!is_array($payload) || $payload === [] || array_is_list($payload)) {
            return [
                'groups' => is_array($payload) ? array_values($payload) : [],
                'withheldEmployeeIds' => [],
                'withheldSiteIds' => [],
            ];
        }

        $groups = $payload['groups'] ?? [];

        return [
            'groups' => is_array($groups) ? array_values($groups) : [],
            'withheldEmployeeIds' => $this->normalizeIdList($payload['withheldEmployeeIds'] ?? []),
            'withheldSiteIds' => $this->normalizeIdList($payload['withheldSiteIds'] ?? []),
        ];
    }

    /**
     * @return array{groups: list<array<string, mixed>>, withheldEmployeeIds: list<string>, withheldSiteIds: list<string>}
     */
    public function normalizeDocument(mixed $groups, mixed $withheldEmployees, mixed $withheldSites): array
    {
        $normalizedGroups = $this->normalizeGroups($groups);
        $memberIds = [];
        $siteIds = [];
        foreach ($normalizedGroups as $group) {
            foreach ($group['memberIds'] as $memberId) {
                $memberIds[$memberId] = true;
            }
            foreach ($group['sites'] as $site) {
                $siteIds[$site['siteId']] = true;
            }
        }

        return [
            'groups' => $normalizedGroups,
            'withheldEmployeeIds' => $this->normalizeIdList($withheldEmployees, $memberIds),
            'withheldSiteIds' => $this->normalizeIdList($withheldSites, $siteIds),
        ];
    }

    /**
     * @param array<string, true> $excluded
     * @return list<string>
     */
    private function normalizeIdList(mixed $ids, array $excluded = []): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $seen = [];
        $normalized = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '' || isset($seen[$id]) || isset($excluded[$id])) {
                continue;
            }
            $seen[$id] = true;
            $normalized[] = $id;
        }

        return $normalized;
    }

    /**
     * Reprend les groupes d’un jour précédent : mêmes personnes, aucun site.
     *
     * @param array<int, mixed> $groups
     * @return list<array{id: string, label: ?string, leaderEmployeeId: ?string, memberIds: list<string>, sites: list<array{siteId: string, description: ?string}>}>
     */
    public function carryGroups(array $groups): array
    {
        $raw = [];
        foreach (array_values($groups) as $group) {
            if (!is_array($group)) {
                continue;
            }
            $raw[] = [
                'id' => $group['id'] ?? null,
                'label' => $group['label'] ?? null,
                'leaderEmployeeId' => $group['leaderEmployeeId'] ?? null,
                'memberIds' => $group['memberIds'] ?? [],
                'sites' => [],
            ];
        }

        return array_values(array_filter(
            $this->normalizeGroups($raw),
            static fn (array $group): bool => $group['memberIds'] !== [],
        ));
    }
}
