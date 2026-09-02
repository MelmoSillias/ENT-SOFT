<?php

namespace App\Stock\Presentation\Api\Controller;

use App\Stock\Application\Command\CreateStockMovement\CreateStockMovementCommand;
use App\Stock\Application\Command\CreateStockMovement\CreateStockMovementHandler;
use App\Stock\Application\Command\DeleteStockMovement\DeleteStockMovementCommand;
use App\Stock\Application\Command\DeleteStockMovement\DeleteStockMovementHandler;
use App\Stock\Application\Command\UpdateStockMovement\UpdateStockMovementCommand;
use App\Stock\Application\Command\UpdateStockMovement\UpdateStockMovementHandler;
use App\Stock\Application\Query\GetStockMovement\GetStockMovementHandler;
use App\Stock\Application\Query\GetStockMovement\GetStockMovementQuery;
use App\Stock\Application\Query\ListStockMovements\ListStockMovementsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stock-movements')]
final class StockMovementController extends AbstractController
{
    #[Route('', name: 'api_stock_movements_list', methods: ['GET'])]
    #[IsGranted('stock.movements.view')]
    public function list(ListStockMovementsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle());
    }

    #[Route('', name: 'api_stock_movements_create', methods: ['POST'])]
    #[IsGranted('stock.movements.create')]
    public function create(Request $request, CreateStockMovementHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateStockMovementCommand(
            date: $data['date'] ?? '',
            quantity: (float) ($data['quantity'] ?? 0),
            unit: $data['unit'] ?? '',
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
            siteId: $data['siteId'] ?? null,
            lines: $data['lines'] ?? [],
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_stock_movements_get', methods: ['GET'])]
    #[IsGranted('stock.movements.view')]
    public function get(string $id, GetStockMovementHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetStockMovementQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_stock_movements_update', methods: ['PUT'])]
    #[IsGranted('stock.movements.update')]
    public function update(string $id, Request $request, UpdateStockMovementHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateStockMovementCommand(
            id: $id,
            date: $data['date'] ?? null,
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            unit: $data['unit'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
            siteId: $data['siteId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_stock_movements_delete', methods: ['DELETE'])]
    #[IsGranted('stock.movements.delete')]
    public function delete(string $id, DeleteStockMovementHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteStockMovementCommand($id))->toArray());
    }
}
