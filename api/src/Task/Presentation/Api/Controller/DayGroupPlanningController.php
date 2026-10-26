<?php

namespace App\Task\Presentation\Api\Controller;

use App\Task\Application\Command\UpsertDayGroupPlanning\UpsertDayGroupPlanningCommand;
use App\Task\Application\Command\UpsertDayGroupPlanning\UpsertDayGroupPlanningHandler;
use App\Task\Application\Query\GetDayGroupPlanning\GetDayGroupPlanningHandler;
use App\Task\Application\Query\GetDayGroupPlanning\GetDayGroupPlanningQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/group-plannings')]
final class DayGroupPlanningController extends AbstractController
{
    #[Route('', name: 'api_group_plannings_get', methods: ['GET'])]
    #[IsGranted('task.tasks.view')]
    public function get(Request $request, GetDayGroupPlanningHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetDayGroupPlanningQuery(
            date: (string) $request->query->get('date', ''),
        )));
    }

    #[Route('', name: 'api_group_plannings_upsert', methods: ['PUT'])]
    #[IsGranted('task.tasks.update')]
    public function upsert(Request $request, UpsertDayGroupPlanningHandler $handler): JsonResponse
    {
        $data = $request->toArray();

        return $this->json($handler->handle(new UpsertDayGroupPlanningCommand(
            date: (string) ($data['date'] ?? ''),
            groups: $data['groups'] ?? [],
            withheldEmployeeIds: $data['withheldEmployeeIds'] ?? [],
            withheldSiteIds: $data['withheldSiteIds'] ?? [],
        )));
    }
}
