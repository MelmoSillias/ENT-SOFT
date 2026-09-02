<?php

namespace App\Document\Application\Command\DeleteDocument;

final readonly class DeleteDocumentCommand
{
    public function __construct(public string $id) {}
}
