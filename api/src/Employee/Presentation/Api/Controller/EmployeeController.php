<?php

namespace App\Employee\Presentation\Api\Controller;

use App\Employee\Application\Command\CreateEmployee\CreateEmployeeCommand;
use App\Employee\Application\Command\CreateEmployee\CreateEmployeeHandler;
use App\Employee\Application\Command\DeleteEmployee\DeleteEmployeeCommand;
use App\Employee\Application\Command\DeleteEmployee\DeleteEmployeeHandler;
use App\Employee\Application\Command\UpdateEmployee\UpdateEmployeeCommand;
use App\Employee\Application\Command\UpdateEmployee\UpdateEmployeeHandler;
use App\Employee\Application\Query\GetEmployee\GetEmployeeHandler;
use App\Employee\Application\Query\GetEmployee\GetEmployeeQuery;
use App\Employee\Application\Query\ListEmployees\ListEmployeesHandler;
use App\Employee\Application\Query\ListEmployees\ListEmployeesQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employees')]
final class EmployeeController extends AbstractController
{
    #[Route('', name: 'api_employees_list', methods: ['GET'])]
    #[IsGranted('employee.employees.view')]
    public function list(Request $request, ListEmployeesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListEmployeesQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_employees_create', methods: ['POST'])]
    #[IsGranted('employee.employees.create')]
    public function create(Request $request, CreateEmployeeHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateEmployeeCommand(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? '',
            function: $data['function'] ?? '',
            address: $data['address'] ?? null,
            userId: $data['userId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_employees_get', methods: ['GET'])]
    #[IsGranted('employee.employees.view')]
    public function get(string $id, GetEmployeeHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetEmployeeQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_employees_update', methods: ['PUT'])]
    #[IsGranted('employee.employees.update')]
    public function update(string $id, Request $request, UpdateEmployeeHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateEmployeeCommand(
            id: $id,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            function: $data['function'] ?? null,
            address: array_key_exists('address', $data) ? $data['address'] : null,
            userId: array_key_exists('userId', $data) ? $data['userId'] : null,
            hasAddress: array_key_exists('address', $data),
            hasUserId: array_key_exists('userId', $data),
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_employees_delete', methods: ['DELETE'])]
    #[IsGranted('employee.employees.delete')]
    public function delete(string $id, DeleteEmployeeHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteEmployeeCommand($id))->toArray());
    }
}
