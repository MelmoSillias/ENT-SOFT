<?php

namespace App\Project\Application\Query\GetProjectDetail;

final readonly class GetProjectDetailQuery
{
    public function __construct(public string $id) {}
}
