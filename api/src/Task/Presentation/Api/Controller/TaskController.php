<?php

namespace App\Task\Presentation\Api\Controller;

use App\Task\Application\Command\CreateTask\CreateTaskCommand;
use App\Task\Application\Command\CreateTask\CreateTaskHandler;
use App\Task\Application\Command\DeleteTask\DeleteTaskCommand;
use App\Task\Application\Command\DeleteTask\DeleteTaskHandler;
use App\Task\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Task\Application\Command\UpdateTask\UpdateTaskHandler;
use App\Task\Application\Query\GetTask\GetTaskHandler;
use App\Task\Application\Query\GetTask\GetTaskQuery;
use App\Task\Application\Query\ListTasks\ListTasksHandler;
use App\Task\Application\Query\ListTasks\ListTasksQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tasks')]
final class TaskController extends AbstractController
{
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    #[IsGranted('task.tasks.view')]
    public function list(Request $request, ListTasksHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListTasksQuery(
            siteId: $request->query->get('siteId'),
            employeeId: $request->query->get('employeeId'),
            status: $request->query->get('status'),
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        )));
    }

    #[Route('', name: 'api_tasks_create', methods: ['POST'])]
    #[IsGranted('task.tasks.create')]
    public function create(Request $request, CreateTaskHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateTaskCommand(
            title: $data['title'] ?? '',
            siteId: $data['siteId'] ?? '',
            status: $data['status'] ?? 'pending',
            description: $data['description'] ?? null,
            dateDue: $data['dateDue'] ?? null,
            employeeId: $data['employeeId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_tasks_get', methods: ['GET'])]
    #[IsGranted('task.tasks.view')]
    public function get(string $id, GetTaskHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetTaskQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_tasks_update', methods: ['PUT'])]
    #[IsGranted('task.tasks.update')]
    public function update(string $id, Request $request, UpdateTaskHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateTaskCommand(
            id: $id,
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            dateDue: $data['dateDue'] ?? null,
            status: $data['status'] ?? null,
            siteId: $data['siteId'] ?? null,
            employeeId: $data['employeeId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_tasks_delete', methods: ['DELETE'])]
    #[IsGranted('task.tasks.delete')]
    public function delete(string $id, DeleteTaskHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteTaskCommand($id))->toArray());
    }
}
