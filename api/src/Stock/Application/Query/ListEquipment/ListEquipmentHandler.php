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
