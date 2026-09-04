<?php

namespace App\Stock\Presentation\Api\Controller;

use App\Stock\Application\Command\CreateEquipment\CreateEquipmentCommand;
use App\Stock\Application\Command\CreateEquipment\CreateEquipmentHandler;
use App\Stock\Application\Command\DeleteEquipment\DeleteEquipmentCommand;
use App\Stock\Application\Command\DeleteEquipment\DeleteEquipmentHandler;
use App\Stock\Application\Command\UpdateEquipment\UpdateEquipmentCommand;
use App\Stock\Application\Command\UpdateEquipment\UpdateEquipmentHandler;
use App\Stock\Application\Query\GetEquipment\GetEquipmentHandler;
use App\Stock\Application\Query\GetEquipment\GetEquipmentQuery;
use App\Stock\Application\Query\ListEquipment\ListEquipmentHandler;
use App\Stock\Application\Query\ListEquipment\ListEquipmentQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/equipment')]
final class EquipmentController extends AbstractController
{
    #[Route('', name: 'api_equipment_list', methods: ['GET'])]
    #[IsGranted('stock.equipment.view')]
    public function list(Request $request, ListEquipmentHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListEquipmentQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_equipment_create', methods: ['POST'])]
    #[IsGranted('stock.equipment.create')]
    public function create(Request $request, CreateEquipmentHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateEquipmentCommand(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            clientId: $data['clientId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_equipment_get', methods: ['GET'])]
    #[IsGranted('stock.equipment.view')]
    public function get(string $id, GetEquipmentHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetEquipmentQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_equipment_update', methods: ['PUT'])]
    #[IsGranted('stock.equipment.update')]
    public function update(string $id, Request $request, UpdateEquipmentHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateEquipmentCommand(
            id: $id,
            title: $data['title'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            clientId: array_key_exists('clientId', $data) ? $data['clientId'] : null,
            hasDescription: array_key_exists('description', $data),
            hasClientId: array_key_exists('clientId', $data),
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_equipment_delete', methods: ['DELETE'])]
    #[IsGranted('stock.equipment.delete')]
    public function delete(string $id, DeleteEquipmentHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteEquipmentCommand($id))->toArray());
    }
}
