<?php

declare(strict_types=1);

// Equipment CRUD (update, delete, list, get) + Stock Movement + Document
w('Stock/Application/Command/UpdateEquipment/UpdateEquipmentCommand.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\UpdateEquipment;

final readonly class UpdateEquipmentCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $clientId = null,
    ) {
    }
}

PHP);

w('Stock/Application/Command/UpdateEquipment/UpdateEquipmentHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\UpdateEquipment;

use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Exception\EquipmentNotFoundException;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
    ) {
    }

    public function handle(UpdateEquipmentCommand $command): EquipmentResponseDto
    {
        $equipment = $this->equipmentRepository->findById(Uuid::fromString($command->id));
        if (null === $equipment || !$equipment->isEnabled()) {
            throw EquipmentNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $equipment->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->description !== null) {
            $equipment->setDescription($command->description);
        }
        if ($command->clientId !== null) {
            $equipment->setClientId($command->clientId !== '' ? Uuid::fromString($command->clientId) : null);
        }

        $this->equipmentRepository->save($equipment);

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

PHP);

w('Stock/Application/Command/DeleteEquipment/DeleteEquipmentCommand.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\DeleteEquipment;

final readonly class DeleteEquipmentCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Stock/Application/Command/DeleteEquipment/DeleteEquipmentHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\DeleteEquipment;

use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Exception\EquipmentNotFoundException;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
    ) {
    }

    public function handle(DeleteEquipmentCommand $command): EquipmentResponseDto
    {
        $equipment = $this->equipmentRepository->findById(Uuid::fromString($command->id));
        if (null === $equipment || !$equipment->isEnabled()) {
            throw EquipmentNotFoundException::withId($command->id);
        }

        $equipment->disable();
        $this->equipmentRepository->save($equipment);

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

PHP);

w('Stock/Application/Query/ListEquipment/ListEquipmentHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Query\ListEquipment;

use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;

final readonly class ListEquipmentQuery
{
    public function __construct(public ?string $search = null) {}
}

final class ListEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListEquipmentQuery $query): array
    {
        return array_map(
            static fn ($e) => EquipmentResponseDto::fromEntity($e)->toArray(),
            $this->equipmentRepository->findAllEnabled($query->search),
        );
    }
}

PHP);

w('Stock/Application/Query/GetEquipment/GetEquipmentQuery.php', <<<'PHP'
<?php

namespace App\Stock\Application\Query\GetEquipment;

final readonly class GetEquipmentQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Stock/Application/Query/GetEquipment/GetEquipmentHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Query\GetEquipment;

use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Exception\EquipmentNotFoundException;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
    ) {
    }

    public function handle(GetEquipmentQuery $query): EquipmentResponseDto
    {
        $equipment = $this->equipmentRepository->findById(Uuid::fromString($query->id));
        if (null === $equipment || !$equipment->isEnabled()) {
            throw EquipmentNotFoundException::withId($query->id);
        }

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

PHP);

w('Stock/Presentation/Api/Controller/EquipmentController.php', <<<'PHP'
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
            description: $data['description'] ?? null,
            clientId: $data['clientId'] ?? null,
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

PHP);

w('Stock/Application/Command/CreateStockMovement/CreateStockMovementCommand.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\CreateStockMovement;

final readonly class CreateStockMovementCommand
{
    /** @param list<array{equipmentId: string, quantity: float}> $lines */
    public function __construct(
        public string $date,
        public float $quantity,
        public string $unit,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
        public array $lines = [],
    ) {
    }
}

PHP);

w('Stock/Application/Command/CreateStockMovement/CreateStockMovementHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\CreateStockMovement;

use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Entity\StockMovement;
use App\Stock\Domain\Entity\StockMovementLine;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(CreateStockMovementCommand $command): StockMovementResponseDto
    {
        $unit = FieldValidator::requireNonEmpty($command->unit, 'Unité');
        $movement = new StockMovement(
            date: new \DateTimeImmutable($command->date),
            quantity: $command->quantity,
            unit: $unit,
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
            siteId: $command->siteId ? Uuid::fromString($command->siteId) : null,
        );
        $this->movementRepository->save($movement);

        $lineDtos = [];
        foreach ($command->lines as $lineData) {
            $line = new StockMovementLine(
                movement: $movement,
                equipmentId: Uuid::fromString($lineData['equipmentId']),
                quantity: (float) $lineData['quantity'],
            );
            $this->lineRepository->save($line);
            $lineDtos[] = StockMovementLineResponseDto::fromEntity($line)->toArray();
        }

        return new StockMovementResponseDto(
            id: (string) $movement->getId(),
            date: $movement->getDate()->format('Y-m-d'),
            quantity: $movement->getQuantity(),
            unit: $movement->getUnit(),
            clientId: $movement->getClientId()?->toRfc4122(),
            projectId: $movement->getProjectId()?->toRfc4122(),
            siteId: $movement->getSiteId()?->toRfc4122(),
            lines: $lineDtos,
            isEnabled: $movement->isEnabled(),
            createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part6d.php';
