<?php

namespace App\Document\Application\Command\DeleteDocument;

use App\Document\Application\Dto\DocumentResponseDto;
use App\Document\Application\Service\DocumentUploadService;
use App\Document\Domain\Exception\DocumentNotFoundException;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentUploadService $uploadService,
    ) {
    }

    public function handle(DeleteDocumentCommand $command): DocumentResponseDto
    {
        $document = $this->documentRepository->findById(Uuid::fromString($command->id));
        if (null === $document || !$document->isEnabled()) {
            throw DocumentNotFoundException::withId($command->id);
        }

        $this->uploadService->deleteFile($document->getFilePath());
        $document->disable();
        $this->documentRepository->save($document);

        return DocumentResponseDto::fromEntity($document);
    }
}
