<?php

namespace App\Site\Presentation\Api\Controller;

use App\Site\Application\Command\CreateSite\CreateSiteCommand;
use App\Site\Application\Command\CreateSite\CreateSiteHandler;
use App\Site\Application\Command\DeleteSite\DeleteSiteCommand;
use App\Site\Application\Command\DeleteSite\DeleteSiteHandler;
use App\Site\Application\Command\UpdateSite\UpdateSiteCommand;
use App\Site\Application\Command\UpdateSite\UpdateSiteHandler;
use App\Site\Application\Query\GetSite\GetSiteHandler;
use App\Site\Application\Query\GetSite\GetSiteQuery;
use App\Site\Application\Query\ListSites\ListSitesHandler;
use App\Site\Application\Query\ListSites\ListSitesQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sites')]
final class SiteController extends AbstractController
{
    #[Route('', name: 'api_sites_list', methods: ['GET'])]
    #[IsGranted('site.sites.view')]
    public function list(Request $request, ListSitesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListSitesQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_sites_create', methods: ['POST'])]
    #[IsGranted('site.sites.create')]
    public function create(Request $request, CreateSiteHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateSiteCommand(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            clientId: $data['clientId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_sites_get', methods: ['GET'])]
    #[IsGranted('site.sites.view')]
    public function get(string $id, GetSiteHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetSiteQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_sites_update', methods: ['PUT'])]
    #[IsGranted('site.sites.update')]
    public function update(string $id, Request $request, UpdateSiteHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateSiteCommand(
            id: $id,
            title: $data['title'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            clientId: $data['clientId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_sites_delete', methods: ['DELETE'])]
    #[IsGranted('site.sites.delete')]
    public function delete(string $id, DeleteSiteHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteSiteCommand($id))->toArray());
    }
}
