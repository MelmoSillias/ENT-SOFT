<?php

namespace App\Employee\Presentation\Api\Controller;

use App\Employee\Application\Command\CheckInEmployeePosition\CheckInEmployeePositionCommand;
use App\Employee\Application\Command\CheckInEmployeePosition\CheckInEmployeePositionHandler;
use App\Employee\Application\Query\ListEmployeePositions\ListEmployeePositionsHandler;
use App\Employee\Application\Query\ListEmployeePositions\ListEmployeePositionsQuery;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employee-positions')]
final class EmployeePositionController extends AbstractController
{
    #[Route('/checkin', name: 'api_employee_positions_checkin', methods: ['POST'])]
    #[IsGranted('employee.positions.checkin')]
    public function checkin(Request $request, CheckInEmployeePositionHandler $handler): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->toArray();
        $latitude = $data['latitude'] ?? $data['lat'] ?? null;
        $longitude = $data['longitude'] ?? $data['lng'] ?? null;
        if (null === $latitude || null === $longitude || !\is_numeric($latitude) || !\is_numeric($longitude)) {
            return $this->json(['error' => 'Latitude et longitude sont requises.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $handler->handle(new CheckInEmployeePositionCommand(
                userId: $user->getId()->toRfc4122(),
                latitude: (float) $latitude,
                longitude: (float) $longitude,
                accuracy: isset($data['accuracy']) && \is_numeric($data['accuracy'])
                    ? (float) $data['accuracy']
                    : null,
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('', name: 'api_employee_positions_list', methods: ['GET'])]
    public function list(
        Request $request,
        ListEmployeePositionsHandler $handler,
        EmployeeRepositoryInterface $employeeRepository,
    ): JsonResponse {
        $canViewAll = $this->isGranted('employee.positions.view');
        $canCheckin = $this->isGranted('employee.positions.checkin');

        if (!$canViewAll && !$canCheckin) {
            throw $this->createAccessDeniedException();
        }

        $latestOnly = \in_array($request->query->get('latestOnly'), ['1', 'true', true], true);
        $limitParam = $request->query->get('limit');
        $limit = null !== $limitParam && '' !== $limitParam ? (int) $limitParam : null;

        $employeeId = $request->query->get('employeeId');

        if (!$canViewAll) {
            $user = $this->getUser();
            if (!$user instanceof Utilisateur) {
                throw $this->createAccessDeniedException();
            }
            $employee = $employeeRepository->findByUserId($user->getId());
            if (null === $employee || !$employee->isEnabled()) {
                return $this->json([]);
            }
            $ownId = $employee->getId()->toRfc4122();
            if (null !== $employeeId && $employeeId !== $ownId) {
                throw $this->createAccessDeniedException();
            }
            $employeeId = $ownId;
            if (null === $limit && !$latestOnly) {
                $limit = 10;
            }
        }

        return $this->json($handler->handle(new ListEmployeePositionsQuery(
            latestOnly: $latestOnly,
            employeeId: \is_string($employeeId) && '' !== $employeeId ? $employeeId : null,
            limit: $limit,
        )));
    }
}
