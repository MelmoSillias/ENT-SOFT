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
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\SharedKernel\Application\Service\AvatarUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/employees')]
final class EmployeeController extends AbstractController
{
    #[Route('', name: 'api_employees_list', methods: ['GET'])]
    #[IsGranted('employee.employees.view')]
    public function list(Request $request, ListEmployeesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListEmployeesQuery(
            search: $request->query->get('search'),
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        )));
    }

    #[Route('', name: 'api_employees_create', methods: ['POST'])]
    #[IsGranted('employee.employees.create')]
    public function create(Request $request, CreateEmployeeHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateEmployeeCommand(
            prenom: $data['prenom'] ?? '',
            nom: $data['nom'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? '',
            roleCode: $data['roleCode'] ?? $data['function'] ?? '',
            address: $data['address'] ?? null,
            mention: $data['mention'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}/photo', name: 'api_employees_photo_upload', methods: ['POST'], priority: 10)]
    #[IsGranted('employee.employees.update')]
    public function uploadPhoto(
        string $id,
        Request $request,
        EmployeeRepositoryInterface $employeeRepository,
        AvatarUploadService $avatarUploadService,
        GetEmployeeHandler $getEmployeeHandler,
    ): JsonResponse {
        $employee = $employeeRepository->findById(Uuid::fromString($id));
        if (null === $employee || !$employee->isEnabled()) {
            return $this->json(['error' => 'Employé introuvable'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Fichier image requis (champ file).'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $photoUrl = $avatarUploadService->upload(AvatarUploadService::TYPE_EMPLOYEES, $employee->getId(), $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $employee->setPhotoUrl($photoUrl);
        $employeeRepository->save($employee);

        return $this->json($getEmployeeHandler->handle(new GetEmployeeQuery($id))->toArray());
    }

    #[Route('/{id}/photo', name: 'api_employees_photo_delete', methods: ['DELETE'], priority: 10)]
    #[IsGranted('employee.employees.update')]
    public function deletePhoto(
        string $id,
        EmployeeRepositoryInterface $employeeRepository,
        AvatarUploadService $avatarUploadService,
        GetEmployeeHandler $getEmployeeHandler,
    ): JsonResponse {
        $employee = $employeeRepository->findById(Uuid::fromString($id));
        if (null === $employee || !$employee->isEnabled()) {
            return $this->json(['error' => 'Employé introuvable'], Response::HTTP_NOT_FOUND);
        }

        $avatarUploadService->clear(AvatarUploadService::TYPE_EMPLOYEES, $employee->getId());
        $employee->setPhotoUrl(null);
        $employeeRepository->save($employee);

        return $this->json($getEmployeeHandler->handle(new GetEmployeeQuery($id))->toArray());
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
            prenom: $data['prenom'] ?? null,
            nom: $data['nom'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            roleCode: $data['roleCode'] ?? $data['function'] ?? null,
            address: array_key_exists('address', $data) ? $data['address'] : null,
            hasAddress: array_key_exists('address', $data),
            mention: array_key_exists('mention', $data) ? $data['mention'] : null,
            hasMention: array_key_exists('mention', $data),
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
