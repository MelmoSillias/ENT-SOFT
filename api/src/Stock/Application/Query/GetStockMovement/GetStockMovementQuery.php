<?php

namespace App\Stock\Application\Query\GetStockMovement;

final readonly class GetStockMovementQuery
{
    public function __construct(public string $id) {}
}
