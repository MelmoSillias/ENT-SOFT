<?php

namespace App\Project\Presentation\Api\Controller;

use App\Project\Application\Service\ProjectDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/project-documents')]
final class GlobalProjectDocumentController extends AbstractController
{
    #[Route('', name: 'api_global_project_documents_list', methods: ['GET'])]
    #[IsGranted('project.projects.view')]
    public function list(Request $request, ProjectDocumentService $service): JsonResponse
    {
        return $this->json($service->list($request->query->get('projectId'), $request->query->get('type')));
    }

    #[Route('', name: 'api_global_project_documents_create', methods: ['POST'])]
    #[IsGranted('project.projects.update')]
    public function create(Request $request, ProjectDocumentService $service): JsonResponse
    {
        return $this->json($service->saveGlobal($request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_global_project_documents_update', methods: ['PUT'])]
    #[IsGranted('project.projects.update')]
    public function update(string $id, Request $request, ProjectDocumentService $service): JsonResponse
    {
        return $this->json($service->saveGlobal($request->toArray(), $id));
    }

    #[Route('/{id}', name: 'api_global_project_documents_delete', methods: ['DELETE'])]
    #[IsGranted('project.projects.delete')]
    public function delete(string $id, ProjectDocumentService $service): Response
    {
        $service->delete(null, $id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
