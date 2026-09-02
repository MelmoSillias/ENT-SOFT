<?php

namespace App\Project\Application\Query\GetProjectDetail;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Project\Application\Dto\ProjectDetailResponseDto;
use App\Project\Application\Dto\ProjectEventResponseDto;
use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectEventRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectDetailHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
        private readonly ProjectEventRepositoryInterface $projectEventRepository,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly SiteRepositoryInterface $siteRepository,
    ) {
    }

    public function handle(GetProjectDetailQuery $query): ProjectDetailResponseDto
    {
        $projectId = Uuid::fromString($query->id);
        $project = $this->projectRepository->findById($projectId);
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($query->id);
        }

        $client = $this->clientRepository->findById($project->getClientId());
        $projectSites = $this->projectSiteRepository->findByProjectId($projectId);
        $siteIds = array_map(static fn ($ps) => $ps->getSiteId(), $projectSites);
        $sitesById = [];
        foreach ($this->siteRepository->findByIds($siteIds) as $site) {
            $sitesById[(string) $site->getId()] = $site;
        }

        $sites = [];
        foreach ($projectSites as $ps) {
            $dto = ProjectSiteResponseDto::fromEntity($ps)->toArray();
            $site = $sitesById[(string) $ps->getSiteId()] ?? null;
            $dto['siteTitle'] = $site?->getTitle();
            $sites[] = $dto;
        }

        $events = array_map(
            static fn ($e) => ProjectEventResponseDto::fromEntity($e)->toArray(),
            $this->projectEventRepository->findByProjectId($projectId),
        );

        return new ProjectDetailResponseDto(
            id: (string) $project->getId(),
            code: $project->getCode(),
            title: $project->getTitle(),
            object: $project->getObject(),
            dateDebut: $project->getDateDebut()?->format('Y-m-d'),
            dateFin: $project->getDateFin()?->format('Y-m-d'),
            status: $project->getStatus()->value,
            budget: $project->getBudget(),
            clientId: (string) $project->getClientId(),
            clientTitle: $client?->getTitle(),
            sitesInformations: $project->getSitesInformations(),
            sites: $sites,
            events: $events,
            isEnabled: $project->isEnabled(),
            createdAt: $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $project->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
