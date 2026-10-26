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

    /** @return array{date: string, groups: list<array<string, mixed>>, withheldEmployeeIds: list<string>, withheldSiteIds: list<string>, updatedAt: string} */
    public function handle(UpsertDayGroupPlanningCommand $command): array
    {
        $date = $this->normalizer->parseDate($command->date);
        $document = $this->normalizer->normalizeDocument(
            $command->groups,
            $command->withheldEmployeeIds,
            $command->withheldSiteIds,
        );
        $planning = $this->planningRepository->findByDate($date);

        if ($planning === null) {
            $planning = new DayGroupPlanning($date, $document);
        } else {
            $planning->replacePayload($document);
        }

        $this->planningRepository->save($planning);

        return [
            'date' => $date->format('Y-m-d'),
            'groups' => $document['groups'],
            'withheldEmployeeIds' => $document['withheldEmployeeIds'],
            'withheldSiteIds' => $document['withheldSiteIds'],
            'updatedAt' => $planning->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
