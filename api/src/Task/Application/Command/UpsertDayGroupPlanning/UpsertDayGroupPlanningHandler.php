<?php

namespace App\Task\Application\Command\UpsertDayGroupPlanning;

use App\Task\Application\Service\DayGroupPlanningNormalizer;
use App\Task\Domain\Entity\DayGroupPlanning;
use App\Task\Domain\Repository\DayGroupPlanningRepositoryInterface;

final class UpsertDayGroupPlanningHandler
{
    public function __construct(
        private readonly DayGroupPlanningRepositoryInterface $planningRepository,
        private readonly DayGroupPlanningNormalizer $normalizer,
    ) {
    }

    /** @return array{date: string, groups: list<array<string, mixed>>, updatedAt: string} */
    public function handle(UpsertDayGroupPlanningCommand $command): array
    {
        $date = $this->normalizer->parseDate($command->date);
        $groups = $this->normalizer->normalizeGroups($command->groups);
        $planning = $this->planningRepository->findByDate($date);

        if ($planning === null) {
            $planning = new DayGroupPlanning($date, $groups);
        } else {
            $planning->replacePayload($groups);
        }

        $this->planningRepository->save($planning);

        return [
            'date' => $date->format('Y-m-d'),
            'groups' => $planning->getPayload(),
            'updatedAt' => $planning->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
