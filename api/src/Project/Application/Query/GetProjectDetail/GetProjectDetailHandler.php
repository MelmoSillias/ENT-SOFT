<?php

namespace App\Project\Application\Query\GetProjectDetail;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\Project\Application\Dto\ProjectDetailResponseDto;
use App\Project\Application\Dto\ProjectEventResponseDto;
use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectEventRepositoryInterface;
use App\Project\Domain\Repository\ProjectLotRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectDetailHandler
{
    /** @var list<string> */
    private const COMMENT_KEYS = ['comment', 'remarques'];

    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
        private readonly ProjectEventRepositoryInterface $projectEventRepository,
        private readonly ProjectLotRepositoryInterface $projectLotRepository,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly SiteRepositoryInterface $siteRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
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

        $lotsById = [];
        $lots = [];
        foreach ($this->projectLotRepository->findByProjectId($projectId) as $lot) {
            $lotsById[(string) $lot->getId()] = $lot;
            $lots[] = [
                'id' => (string) $lot->getId(),
                'code' => $lot->getCode(),
                'title' => $lot->getTitle(),
            ];
        }

        $employeeIdsToLoad = [];
        foreach ($projectSites as $ps) {
            if (null !== $ps->getTechnicianId()) {
                $employeeIdsToLoad[] = $ps->getTechnicianId();
            }
            foreach ($ps->getEmployeeIds() as $empId) {
                if (is_string($empId) && $empId !== '') {
                    $employeeIdsToLoad[] = Uuid::fromString($empId);
                }
            }
        }
        $uniqueEmployeeIds = [];
        foreach ($employeeIdsToLoad as $eid) {
            $uniqueEmployeeIds[(string) $eid] = $eid;
        }
        $employeesById = [];
        foreach ($this->employeeRepository->findByIds(array_values($uniqueEmployeeIds)) as $employee) {
            $employeesById[(string) $employee->getId()] = $employee;
        }

        $sites = [];
        foreach ($projectSites as $ps) {
            $dto = ProjectSiteResponseDto::fromEntity($ps)->toArray();
            $site = $sitesById[(string) $ps->getSiteId()] ?? null;
            $dto['siteCode'] = $site?->getCode();
            $dto['siteTitle'] = $site?->getTitle();

            $lot = $ps->getLotId() ? ($lotsById[(string) $ps->getLotId()] ?? null) : null;
            $dto['lotCode'] = $lot?->getCode();
            $dto['lotTitle'] = $lot?->getTitle();

            $techId = $ps->getTechnicianId();
            $dto['technicianName'] = $techId !== null
                ? (($employeesById[(string) $techId] ?? null)?->getName())
                : null;

            $technicians = [];
            foreach ($ps->getEmployeeIds() as $empId) {
                if (!is_string($empId) || $empId === '') {
                    continue;
                }
                $emp = $employeesById[$empId] ?? null;
                if ($emp !== null) {
                    $technicians[] = [
                        'id' => $empId,
                        'name' => $emp->getName(),
                    ];
                } else {
                    $technicians[] = [
                        'id' => $empId,
                        'name' => $empId,
                    ];
                }
            }
            $dto['technicians'] = $technicians;

            $dto['comment'] = $this->extractComment($dto['informationsValues']);

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
            lots: $lots,
            sites: $sites,
            events: $events,
            isEnabled: $project->isEnabled(),
            createdAt: $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $project->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @param array<string, mixed> $informationsValues */
    private function extractComment(array $informationsValues): ?string
    {
        foreach (self::COMMENT_KEYS as $key) {
            $value = $informationsValues[$key] ?? null;
            if (null !== $value && '' !== $value) {
                return (string) $value;
            }
        }

        return null;
    }
}
