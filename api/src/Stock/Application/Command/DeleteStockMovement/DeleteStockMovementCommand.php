<?php

namespace App\Stock\Application\Command\DeleteStockMovement;

final readonly class DeleteStockMovementCommand
{
    public function __construct(public string $id) {}
}
