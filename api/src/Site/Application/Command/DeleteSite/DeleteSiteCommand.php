<?php

namespace App\Site\Application\Command\DeleteSite;

final readonly class DeleteSiteCommand
{
    public function __construct(public string $id) {}
}
