<?php

declare(strict_types=1);

w('Project/Application/Query/ListProjects/ListProjectsQuery.php', <<<'PHP'
<?php

namespace App\Project\Application\Query\ListProjects;

final readonly class ListProjectsQuery
{
    public function __construct(public ?string $search = null) {}
}

PHP);

w('Project/Application/Query/ListProjects/ListProjectsHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Query\ListProjects;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Repository\ProjectRepositoryInterface;

final class ListProjectsHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListProjectsQuery $query): array
    {
        return array_map(
            static fn ($p) => ProjectResponseDto::fromEntity($p)->toArray(),
            $this->projectRepository->findAllEnabled($query->search),
        );
    }
}

PHP);

w('Project/Application/Query/GetProject/GetProjectQuery.php', <<<'PHP'
<?php

namespace App\Project\Application\Query\GetProject;

final readonly class GetProjectQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Project/Application/Query/GetProject/GetProjectHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Query\GetProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(GetProjectQuery $query): ProjectResponseDto
    {
        $project = $this->projectRepository->findById(Uuid::fromString($query->id));
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($query->id);
        }

        return ProjectResponseDto::fromEntity($project);
    }
}

PHP);

w('Project/Application/Query/GetProjectDetail/GetProjectDetailQuery.php', <<<'PHP'
<?php

namespace App\Project\Application\Query\GetProjectDetail;

final readonly class GetProjectDetailQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Project/Application/Query/GetProjectDetail/GetProjectDetailHandler.php', <<<'PHP'
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

PHP);

w('Project/Presentation/Api/Controller/ProjectController.php', <<<'PHP'
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
            object: $data['object'] ?? null,
            dateDebut: $data['dateDebut'] ?? null,
            dateFin: $data['dateFin'] ?? null,
            status: $data['status'] ?? null,
            budget: isset($data['budget']) ? (float) $data['budget'] : null,
            clientId: $data['clientId'] ?? null,
            sitesInformations: $data['sitesInformations'] ?? null,
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

PHP);

// ========== EMPLOYEE MODULE ==========

w('Employee/Domain/Entity/Employee.php', <<<'PHP'
<?php

namespace App\Employee\Domain\Entity;

use App\Employee\Infrastructure\Persistence\Doctrine\DoctrineEmployeeRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineEmployeeRepository::class)]
#[ORM\Table(name: 'employees')]
class Employee
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 50)]
    private string $phone;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address;

    #[ORM\Column(length: 100)]
    private string $function;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId;

    public function __construct(
        string $name,
        string $email,
        string $phone,
        string $function,
        ?string $address = null,
        ?Uuid $userId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->function = $function;
        $this->address = $address;
        $this->userId = $userId;
    }

    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getAddress(): ?string { return $this->address; }
    public function getFunction(): string { return $this->function; }
    public function getUserId(): ?Uuid { return $this->userId; }

    public function setName(string $name): void { $this->name = $name; $this->touch(); }
    public function setEmail(string $email): void { $this->email = $email; $this->touch(); }
    public function setPhone(string $phone): void { $this->phone = $phone; $this->touch(); }
    public function setAddress(?string $address): void { $this->address = $address; $this->touch(); }
    public function setFunction(string $function): void { $this->function = $function; $this->touch(); }
    public function setUserId(?Uuid $userId): void { $this->userId = $userId; $this->touch(); }
}

PHP);

writeException('Employee', 'Employee', 'Employé');

w('Employee/Domain/Repository/EmployeeRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Employee\Domain\Repository;

use App\Employee\Domain\Entity\Employee;
use Symfony\Component\Uid\Uuid;

interface EmployeeRepositoryInterface
{
    public function save(Employee $employee): void;

    public function findById(Uuid $id): ?Employee;

    /** @return list<Employee> */
    public function findAllEnabled(?string $search = null): array;
}

PHP);

w('Employee/Infrastructure/Persistence/Doctrine/DoctrineEmployeeRepository.php', <<<'PHP'
<?php

namespace App\Employee\Infrastructure\Persistence\Doctrine;

use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Employee> */
class DoctrineEmployeeRepository extends ServiceEntityRepository implements EmployeeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function save(Employee $employee): void
    {
        $this->getEntityManager()->persist($employee);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Employee
    {
        return $this->find($id);
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('e.name', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('e.name LIKE :search OR e.email LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }
}

PHP);

w('Employee/Application/Dto/EmployeeResponseDto.php', <<<'PHP'
<?php

namespace App\Employee\Application\Dto;

use App\Employee\Domain\Entity\Employee;

final readonly class EmployeeResponseDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $phone,
        public ?string $address,
        public string $function,
        public ?string $userId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Employee $employee): self
    {
        return new self(
            id: (string) $employee->getId(),
            name: $employee->getName(),
            email: $employee->getEmail(),
            phone: $employee->getPhone(),
            address: $employee->getAddress(),
            function: $employee->getFunction(),
            userId: $employee->getUserId()?->toRfc4122(),
            isEnabled: $employee->isEnabled(),
            createdAt: $employee->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $employee->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'function' => $this->function,
            'userId' => $this->userId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part3e.php';
