<?php

namespace App\Prestataire\Presentation\Api\Controller;

use App\Prestataire\Application\Command\ChangePrestationStatus\ChangePrestationStatusCommand;
use App\Prestataire\Application\Command\ChangePrestationStatus\ChangePrestationStatusHandler;
use App\Prestataire\Application\Command\CreatePrestataire\CreatePrestataireCommand;
use App\Prestataire\Application\Command\CreatePrestataire\CreatePrestataireHandler;
use App\Prestataire\Application\Command\CreatePrestation\CreatePrestationCommand;
use App\Prestataire\Application\Command\CreatePrestation\CreatePrestationHandler;
use App\Prestataire\Application\Command\DeletePrestataire\DeletePrestataireCommand;
use App\Prestataire\Application\Command\DeletePrestataire\DeletePrestataireHandler;
use App\Prestataire\Application\Command\DeletePrestation\DeletePrestationCommand;
use App\Prestataire\Application\Command\DeletePrestation\DeletePrestationHandler;
use App\Prestataire\Application\Command\DuplicatePrestation\DuplicatePrestationCommand;
use App\Prestataire\Application\Command\DuplicatePrestation\DuplicatePrestationHandler;
use App\Prestataire\Application\Command\PayPrestation\PayPrestationCommand;
use App\Prestataire\Application\Command\PayPrestation\PayPrestationHandler;
use App\Prestataire\Application\Command\ResetPrestationPayments\ResetPrestationPaymentsCommand;
use App\Prestataire\Application\Command\ResetPrestationPayments\ResetPrestationPaymentsHandler;
use App\Prestataire\Application\Command\UpdatePrestataire\UpdatePrestataireCommand;
use App\Prestataire\Application\Command\UpdatePrestataire\UpdatePrestataireHandler;
use App\Prestataire\Application\Command\UpdatePrestation\UpdatePrestationCommand;
use App\Prestataire\Application\Command\UpdatePrestation\UpdatePrestationHandler;
use App\Prestataire\Application\Query\GetPrestataire\GetPrestataireHandler;
use App\Prestataire\Application\Query\GetPrestataire\GetPrestataireQuery;
use App\Prestataire\Application\Query\GetPrestation\GetPrestationHandler;
use App\Prestataire\Application\Query\GetPrestation\GetPrestationQuery;
use App\Prestataire\Application\Query\ListPrestataires\ListPrestatairesHandler;
use App\Prestataire\Application\Query\ListPrestataires\ListPrestatairesQuery;
use App\Prestataire\Application\Query\ListPrestationsByPrestataire\ListPrestationsByPrestataireHandler;
use App\Prestataire\Application\Query\ListPrestationsByPrestataire\ListPrestationsByPrestataireQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/prestataires')]
final class PrestataireController extends AbstractController
{
    #[Route('', name: 'api_prestataires_list', methods: ['GET'])]
    #[IsGranted('employee.prestataires.view')]
    public function list(Request $request, ListPrestatairesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListPrestatairesQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_prestataires_create', methods: ['POST'])]
    #[IsGranted('employee.prestataires.create')]
    public function create(Request $request, CreatePrestataireHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreatePrestataireCommand(
            prenom: $data['prenom'] ?? '',
            nom: $data['nom'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? '',
            address: $data['address'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/prestations/{prestationId}', name: 'api_prestations_get', methods: ['GET'], priority: 10)]
    #[IsGranted('employee.prestataires.view')]
    public function getPrestation(string $prestationId, GetPrestationHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetPrestationQuery($prestationId))->toArray());
    }

    #[Route('/prestations/{prestationId}', name: 'api_prestations_update', methods: ['PUT'], priority: 10)]
    #[IsGranted('employee.prestataires.update')]
    public function updatePrestation(string $prestationId, Request $request, UpdatePrestationHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdatePrestationCommand(
            id: $prestationId,
            description: $data['description'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            siteId: array_key_exists('siteId', $data) ? ($data['siteId'] ?? null) : null,
            hasSiteId: array_key_exists('siteId', $data),
            workStatus: $data['workStatus'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/prestations/{prestationId}', name: 'api_prestations_delete', methods: ['DELETE'], priority: 10)]
    #[IsGranted('employee.prestataires.delete')]
    public function deletePrestation(string $prestationId, DeletePrestationHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeletePrestationCommand($prestationId))->toArray());
    }

    #[Route('/prestations/{prestationId}/pay', name: 'api_prestations_pay', methods: ['POST'], priority: 10)]
    #[IsGranted('employee.prestations.pay')]
    public function payPrestation(string $prestationId, Request $request, PayPrestationHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new PayPrestationCommand(
            id: $prestationId,
            amount: (float) ($data['amount'] ?? 0),
            date: $data['date'] ?? null,
            description: $data['description'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/prestations/{prestationId}/status', name: 'api_prestations_status', methods: ['PATCH'], priority: 10)]
    #[IsGranted('employee.prestataires.update')]
    public function changePrestationStatus(string $prestationId, Request $request, ChangePrestationStatusHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new ChangePrestationStatusCommand(
            id: $prestationId,
            workStatus: $data['workStatus'] ?? '',
        ));

        return $this->json($result->toArray());
    }

    #[Route('/prestations/{prestationId}/duplicate', name: 'api_prestations_duplicate', methods: ['POST'], priority: 10)]
    #[IsGranted('employee.prestataires.create')]
    public function duplicatePrestation(string $prestationId, DuplicatePrestationHandler $handler): JsonResponse
    {
        return $this->json(
            $handler->handle(new DuplicatePrestationCommand($prestationId))->toArray(),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/prestations/{prestationId}/reset-payments', name: 'api_prestations_reset_payments', methods: ['POST'], priority: 10)]
    #[IsGranted('employee.prestataires.update')]
    public function resetPrestationPayments(string $prestationId, ResetPrestationPaymentsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ResetPrestationPaymentsCommand($prestationId))->toArray());
    }

    #[Route('/{id}/prestations', name: 'api_prestataires_prestations_list', methods: ['GET'])]
    #[IsGranted('employee.prestataires.view')]
    public function listPrestations(string $id, ListPrestationsByPrestataireHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListPrestationsByPrestataireQuery($id)));
    }

    #[Route('/{id}/prestations', name: 'api_prestataires_prestations_create', methods: ['POST'])]
    #[IsGranted('employee.prestataires.create')]
    public function createPrestation(string $id, Request $request, CreatePrestationHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreatePrestationCommand(
            prestataireId: $id,
            description: $data['description'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            siteId: $data['siteId'] ?? null,
            workStatus: $data['workStatus'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_prestataires_get', methods: ['GET'])]
    #[IsGranted('employee.prestataires.view')]
    public function get(string $id, GetPrestataireHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetPrestataireQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_prestataires_update', methods: ['PUT'])]
    #[IsGranted('employee.prestataires.update')]
    public function update(string $id, Request $request, UpdatePrestataireHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdatePrestataireCommand(
            id: $id,
            prenom: $data['prenom'] ?? null,
            nom: $data['nom'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: array_key_exists('address', $data) ? $data['address'] : null,
            hasAddress: array_key_exists('address', $data),
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_prestataires_delete', methods: ['DELETE'])]
    #[IsGranted('employee.prestataires.delete')]
    public function delete(string $id, DeletePrestataireHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeletePrestataireCommand($id))->toArray());
    }
}
