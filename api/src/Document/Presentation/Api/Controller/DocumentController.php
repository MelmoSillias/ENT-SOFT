<?php

namespace App\Document\Presentation\Api\Controller;

use App\Document\Application\Command\DeleteDocument\DeleteDocumentCommand;
use App\Document\Application\Command\DeleteDocument\DeleteDocumentHandler;
use App\Document\Application\Query\ListDocumentsByOwner\ListDocumentsByOwnerHandler;
use App\Document\Application\Query\ListDocumentsByOwner\ListDocumentsByOwnerQuery;
use App\Document\Application\Service\DocumentUploadService;
use App\Document\Application\Dto\DocumentResponseDto;
use App\Document\Domain\Enum\DocumentOwnerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/documents')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'api_documents_list', methods: ['GET'])]
    #[IsGranted('document.documents.view')]
    public function list(Request $request, ListDocumentsByOwnerHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListDocumentsByOwnerQuery(
            ownerType: (string) $request->query->get('ownerType', ''),
            ownerId: (string) $request->query->get('ownerId', ''),
        )));
    }

    #[Route('/upload', name: 'api_documents_upload', methods: ['POST'])]
    #[IsGranted('document.documents.upload')]
    public function upload(Request $request, DocumentUploadService $uploadService): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['error' => 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $document = $uploadService->upload(
            file: $file,
            title: (string) $request->request->get('title', $file->getClientOriginalName()),
            ownerType: DocumentOwnerType::from((string) $request->request->get('ownerType', 'client')),
            ownerId: Uuid::fromString((string) $request->request->get('ownerId', '')),
            description: $request->request->get('description'),
        );

        return $this->json(DocumentResponseDto::fromEntity($document)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_documents_delete', methods: ['DELETE'])]
    #[IsGranted('document.documents.delete')]
    public function delete(string $id, DeleteDocumentHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteDocumentCommand($id))->toArray());
    }
}
