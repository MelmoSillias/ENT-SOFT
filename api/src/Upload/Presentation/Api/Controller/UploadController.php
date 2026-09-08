<?php

namespace App\Upload\Presentation\Api\Controller;

use App\Upload\Application\Service\UploadSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/uploads')]
final class UploadController extends AbstractController
{
    public function __construct(private readonly UploadSessionService $uploads)
    {
    }

    #[Route('', name: 'api_uploads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true) ?? [];

        return $this->json($this->uploads->create($data), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_uploads_status', methods: ['GET'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function status(string $id): JsonResponse
    {
        return $this->json($this->uploads->status($id));
    }

    #[Route('/{id}/chunks/{index}', name: 'api_uploads_chunk', methods: ['PUT'], requirements: ['id' => '[a-f0-9]{32}', 'index' => '\d+'])]
    public function chunk(string $id, int $index, Request $request): JsonResponse
    {
        return $this->json($this->uploads->putChunk($id, $index, $request->getContent() ?: ''));
    }

    #[Route('/{id}/complete', name: 'api_uploads_complete', methods: ['POST'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function complete(string $id): JsonResponse
    {
        return $this->json($this->uploads->complete($id));
    }

    #[Route('/{id}', name: 'api_uploads_abort', methods: ['DELETE'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function abort(string $id): JsonResponse
    {
        return $this->json($this->uploads->abort($id));
    }
}
