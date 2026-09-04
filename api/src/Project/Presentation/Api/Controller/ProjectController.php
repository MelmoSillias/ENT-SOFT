<?php

namespace App\Project\Presentation\Api\Controller;

use App\Project\Application\Command\AddSiteToProject\AddSiteToProjectCommand;
use App\Project\Application\Command\AddSiteToProject\AddSiteToProjectHandler;
use App\Project\Application\Command\CreateProject\CreateProjectCommand;
use App\Project\Application\Command\CreateProject\CreateProjectHandler;
use App\Project\Application\Command\CreateProjectEvent\CreateProjectEventCommand;
use App\Project\Application\Command\CreateProjectEvent\CreateProjectEventHandler;
use App\Project\Application\Command\DeleteProject\DeleteProjectCommand;
use App\Project\Application\Command\DeleteProject\DeleteProjectHandler;
use App\Project\Application\Command\RemoveSiteFromProject\RemoveSiteFromProjectCommand;
use App\Project\Application\Command\RemoveSiteFromProject\RemoveSiteFromProjectHandler;
use App\Project\Application\Command\UpdateProject\UpdateProjectCommand;
use App\Project\Application\Command\UpdateProject\UpdateProjectHandler;
use App\Project\Application\Command\UpdateProjectSite\UpdateProjectSiteCommand;
use App\Project\Application\Command\UpdateProjectSite\UpdateProjectSiteHandler;
use App\Project\Application\Query\GetProject\GetProjectHandler;
use App\Project\Application\Query\GetProject\GetProjectQuery;
use App\Project\Application\Query\GetProjectDetail\GetProjectDetailHandler;
use App\Project\Application\Query\GetProjectDetail\GetProjectDetailQuery;
use App\Project\Application\Query\ListProjects\ListProjectsHandler;
use App\Project\Application\Query\ListProjects\ListProjectsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/projects')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'api_projects_list', methods: ['GET'])]
    #[IsGranted('project.projects.view')]
    public function list(Request $request, ListProjectsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListProjectsQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_projects_create', methods: ['POST'])]
    #[IsGranted('project.projects.create')]
    public function create(Request $request, CreateProjectHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateProjectCommand(
            title: $data['title'] ?? '',
            clientId: $data['clientId'] ?? '',
            object: $data['object'] ?? null,
            dateDebut: $data['dateDebut'] ?? null,
            dateFin: $data['dateFin'] ?? null,
            status: $data['status'] ?? 'draft',
            budget: (float) ($data['budget'] ?? 0),
            sitesInformations: $data['sitesInformations'] ?? [],
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_projects_get', methods: ['GET'])]
    #[IsGranted('project.projects.view')]
    public function get(string $id, GetProjectHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetProjectQuery($id))->toArray());
    }

    #[Route('/{id}/detail', name: 'api_projects_detail', methods: ['GET'])]
    #[IsGranted('project.projects.view')]
    public function detail(string $id, GetProjectDetailHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetProjectDetailQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_projects_update', methods: ['PUT'])]
    #[IsGranted('project.projects.update')]
    public function update(string $id, Request $request, UpdateProjectHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateProjectCommand(
            id: $id,
            title: $data['title'] ?? null,
            object: array_key_exists('object', $data) ? $data['object'] : null,
            dateDebut: array_key_exists('dateDebut', $data) ? $data['dateDebut'] : null,
            dateFin: array_key_exists('dateFin', $data) ? $data['dateFin'] : null,
            status: $data['status'] ?? null,
            budget: isset($data['budget']) ? (float) $data['budget'] : null,
            clientId: $data['clientId'] ?? null,
            sitesInformations: $data['sitesInformations'] ?? null,
            hasObject: array_key_exists('object', $data),
            hasDateDebut: array_key_exists('dateDebut', $data),
            hasDateFin: array_key_exists('dateFin', $data),
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_projects_delete', methods: ['DELETE'])]
    #[IsGranted('project.projects.delete')]
    public function delete(string $id, DeleteProjectHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteProjectCommand($id))->toArray());
    }

    #[Route('/{id}/sites', name: 'api_projects_sites_add', methods: ['POST'])]
    #[IsGranted('project.sites.manage')]
    public function addSite(string $id, Request $request, AddSiteToProjectHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new AddSiteToProjectCommand(
            projectId: $id,
            siteId: $data['siteId'] ?? '',
            status: $data['status'] ?? 'pending',
            informationsValues: $data['informationsValues'] ?? [],
            employeeIds: $data['employeeIds'] ?? [],
            lotId: $data['lotId'] ?? null,
            technicianId: $data['technicianId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/sites/{id}', name: 'api_projects_sites_update', methods: ['PUT'])]
    #[IsGranted('project.sites.manage')]
    public function updateSite(string $id, Request $request, UpdateProjectSiteHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateProjectSiteCommand(
            id: $id,
            status: $data['status'] ?? null,
            informationsValues: $data['informationsValues'] ?? null,
            employeeIds: $data['employeeIds'] ?? null,
            lotId: array_key_exists('lotId', $data) ? ($data['lotId'] ?? '') : null,
            technicianId: array_key_exists('technicianId', $data) ? ($data['technicianId'] ?? '') : null,
            clearLotId: array_key_exists('lotId', $data) && ($data['lotId'] === null || $data['lotId'] === ''),
            clearTechnicianId: array_key_exists('technicianId', $data) && ($data['technicianId'] === null || $data['technicianId'] === ''),
        ));

        return $this->json($result->toArray());
    }

    #[Route('/sites/{id}', name: 'api_projects_sites_remove', methods: ['DELETE'])]
    #[IsGranted('project.sites.manage')]
    public function removeSite(string $id, RemoveSiteFromProjectHandler $handler): JsonResponse
    {
        $handler->handle(new RemoveSiteFromProjectCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/events', name: 'api_projects_events_create', methods: ['POST'])]
    #[IsGranted('project.events.create')]
    public function createEvent(string $id, Request $request, CreateProjectEventHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateProjectEventCommand(
            projectId: $id,
            date: $data['date'] ?? '',
            title: $data['title'] ?? '',
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }
}
