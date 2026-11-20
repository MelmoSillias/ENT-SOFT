<?php

namespace App\Project\Presentation\Api\Controller;

use App\Impression\Application\Service\ProjectDocumentImpressionService;
use App\Project\Application\Service\ProjectDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/projects/{projectId}/documents')]
final class ProjectDocumentController extends AbstractController
{
    #[Route('', name: 'api_project_documents_list', methods: ['GET'])]
    #[IsGranted('project.projects.view')]
    public function list(string $projectId, Request $request, ProjectDocumentService $service): JsonResponse
    {
        return $this->json($service->list($projectId, $request->query->get('type')));
    }

    #[Route('', name: 'api_project_documents_create', methods: ['POST'])]
    #[IsGranted('project.projects.update')]
    public function create(string $projectId, Request $request, ProjectDocumentService $service): JsonResponse
    {
        return $this->json($service->save($projectId, $request->toArray()), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_project_documents_update', methods: ['PUT'])]
    #[IsGranted('project.projects.update')]
    public function update(string $projectId, string $id, Request $request, ProjectDocumentService $service): JsonResponse
    {
        return $this->json($service->save($projectId, $request->toArray(), $id));
    }

    #[Route('/{id}', name: 'api_project_documents_delete', methods: ['DELETE'])]
    #[IsGranted('project.projects.delete')]
    public function delete(string $projectId, string $id, ProjectDocumentService $service): Response
    {
        $service->delete($projectId, $id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/print', name: 'api_project_documents_print', methods: ['GET'])]
    #[IsGranted('project.projects.view')]
    #[IsGranted('impression.documents.print')]
    public function print(string $projectId, string $id, Request $request, ProjectDocumentImpressionService $service): Response
    {
        return $service->render(
            $projectId, $id,
            (string) $request->query->get('format', 'html'),
            (string) $request->query->get('page', 'a4'),
            (string) $request->query->get('orientation', 'portrait'),
            (string) $request->query->get('disposition', 'inline'),
            $request->query->get('template'),
        );
    }
}
