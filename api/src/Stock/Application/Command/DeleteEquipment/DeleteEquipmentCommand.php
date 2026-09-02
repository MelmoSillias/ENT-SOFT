<?php

namespace App\Stock\Application\Command\DeleteEquipment;

final readonly class DeleteEquipmentCommand
{
    public function __construct(public string $id) {}
}
