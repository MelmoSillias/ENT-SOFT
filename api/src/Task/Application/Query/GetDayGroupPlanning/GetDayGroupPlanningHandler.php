<?php

namespace App\Task\Application\Query\GetDayGroupPlanning;

use App\Task\Application\Service\DayGroupPlanningNormalizer;
use App\Task\Domain\Repository\DayGroupPlanningRepositoryInterface;

final class GetDayGroupPlanningHandler
{
    public function __construct(
        private readonly DayGroupPlanningRepositoryInterface $planningRepository,
        private readonly DayGroupPlanningNormalizer $normalizer,
    ) {
    }

    /** @return array{date: string, groups: list<array<string, mixed>>, withheldEmployeeIds: list<string>, withheldSiteIds: list<string>, updatedAt: ?string, carriedOver: bool, carriedFrom: ?string} */
    public function handle(GetDayGroupPlanningQuery $query): array
    {
        $date = $this->normalizer->parseDate($query->date);
        $planning = $this->planningRepository->findByDate($date);
        if ($planning !== null) {
            $stored = $this->normalizer->readStored($planning->getPayload());

            return [
                'date' => $date->format('Y-m-d'),
                'groups' => $stored['groups'],
                'withheldEmployeeIds' => $stored['withheldEmployeeIds'],
                'withheldSiteIds' => $stored['withheldSiteIds'],
                'updatedAt' => $planning->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'carriedOver' => false,
                'carriedFrom' => null,
            ];
        }

        $previous = $this->planningRepository->findLatestBefore($date);
        $groups = [];
        $carriedFrom = null;
        if ($previous !== null) {
            $stored = $this->normalizer->readStored($previous->getPayload());
            $groups = $this->normalizer->carryGroups($stored['groups']);
            if ($groups !== []) {
                $carriedFrom = $previous->getPlanDate()->format('Y-m-d');
            }
        }

        return [
            'date' => $date->format('Y-m-d'),
            'groups' => $groups,
            'withheldEmployeeIds' => [],
            'withheldSiteIds' => [],
            'updatedAt' => null,
            'carriedOver' => $carriedFrom !== null,
            'carriedFrom' => $carriedFrom,
        ];
    }
}
