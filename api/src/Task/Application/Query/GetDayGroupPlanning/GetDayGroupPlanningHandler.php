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

    /** @return array{date: string, groups: list<array<string, mixed>>, updatedAt: ?string} */
    public function handle(GetDayGroupPlanningQuery $query): array
    {
        $date = $this->normalizer->parseDate($query->date);
        $planning = $this->planningRepository->findByDate($date);
        $groups = $planning?->getPayload() ?? [];
        if (!is_array($groups) || !array_is_list($groups)) {
            $groups = [];
        }

        return [
            'date' => $date->format('Y-m-d'),
            'groups' => $groups,
            'updatedAt' => $planning?->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
