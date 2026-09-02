<?php

declare(strict_types=1);

// ========== SITE MODULE ==========

w('Site/Domain/Entity/Site.php', <<<'PHP'
<?php

namespace App\Site\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Site\Infrastructure\Persistence\Doctrine\DoctrineSiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineSiteRepository::class)]
#[ORM\Table(name: 'sites')]
#[ORM\UniqueConstraint(name: 'uniq_site_code', fields: ['code'])]
class Site
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $clientId;

    public function __construct(string $code, string $title, ?string $description = null, ?Uuid $clientId = null)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->description = $description;
        $this->clientId = $clientId;
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getClientId(): ?Uuid { return $this->clientId; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setClientId(?Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
}

PHP);

writeException('Site', 'Site', 'Site');

w('Site/Domain/Repository/SiteRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Site\Domain\Repository;

use App\Site\Domain\Entity\Site;
use Symfony\Component\Uid\Uuid;

interface SiteRepositoryInterface
{
    public function save(Site $site): void;

    public function findById(Uuid $id): ?Site;

    /** @return list<Site> */
    public function findAllEnabled(?string $search = null): array;

    /** @return list<Site> */
    public function findByIds(array $ids): array;
}

PHP);

w('Site/Infrastructure/Persistence/Doctrine/DoctrineSiteRepository.php', <<<'PHP'
<?php

namespace App\Site\Infrastructure\Persistence\Doctrine;

use App\Site\Domain\Entity\Site;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Site> */
class DoctrineSiteRepository extends ServiceEntityRepository implements SiteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function save(Site $site): void
    {
        $this->getEntityManager()->persist($site);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Site
    {
        return $this->find($id);
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('s.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('s.title LIKE :search OR s.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Site/Application/Dto/SiteResponseDto.php', <<<'PHP'
<?php

namespace App\Site\Application\Dto;

use App\Site\Domain\Entity\Site;

final readonly class SiteResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $description,
        public ?string $clientId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Site $site): self
    {
        return new self(
            id: (string) $site->getId(),
            code: $site->getCode(),
            title: $site->getTitle(),
            description: $site->getDescription(),
            clientId: $site->getClientId()?->toRfc4122(),
            isEnabled: $site->isEnabled(),
            createdAt: $site->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $site->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'clientId' => $this->clientId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

generateSimpleCrud('Site', 'Site', 'sites', '/api/sites', 'site.sites', [
    'create' => ['title' => 'string', 'description' => '?string', 'clientId' => '?string'],
    'update' => ['title' => '?string', 'description' => '?string', 'clientId' => '?string'],
], codeGen: 'SITE', hasClientId: true);

// ========== PROJECT MODULE ==========

w('Project/Domain/Enum/ProjectStatus.php', <<<'PHP'
<?php

namespace App\Project\Domain\Enum;

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

PHP);

w('Project/Domain/Enum/ProjectSiteStatus.php', <<<'PHP'
<?php

namespace App\Project\Domain\Enum;

enum ProjectSiteStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case BLOCKED = 'blocked';
}

PHP);

w('Project/Domain/Entity/Project.php', <<<'PHP'
<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\UniqueConstraint(name: 'uniq_project_code', fields: ['code'])]
class Project
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $object;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDebut;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFin;

    #[ORM\Column(enumType: ProjectStatus::class)]
    private ProjectStatus $status;

    #[ORM\Column(type: 'float')]
    private float $budget;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $sitesInformations = [];

    public function __construct(
        string $code,
        string $title,
        Uuid $clientId,
        ProjectStatus $status = ProjectStatus::DRAFT,
        ?string $object = null,
        ?\DateTimeImmutable $dateDebut = null,
        ?\DateTimeImmutable $dateFin = null,
        float $budget = 0.0,
        array $sitesInformations = [],
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->clientId = $clientId;
        $this->status = $status;
        $this->object = $object;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->budget = $budget;
        $this->sitesInformations = $sitesInformations;
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getObject(): ?string { return $this->object; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function getStatus(): ProjectStatus { return $this->status; }
    public function getBudget(): float { return $this->budget; }
    public function getClientId(): Uuid { return $this->clientId; }
    /** @return list<array<string, mixed>> */
    public function getSitesInformations(): array { return $this->sitesInformations; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setObject(?string $object): void { $this->object = $object; $this->touch(); }
    public function setDateDebut(?\DateTimeImmutable $dateDebut): void { $this->dateDebut = $dateDebut; $this->touch(); }
    public function setDateFin(?\DateTimeImmutable $dateFin): void { $this->dateFin = $dateFin; $this->touch(); }
    public function setStatus(ProjectStatus $status): void { $this->status = $status; $this->touch(); }
    public function setBudget(float $budget): void { $this->budget = $budget; $this->touch(); }
    public function setClientId(Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
    /** @param list<array<string, mixed>> $sitesInformations */
    public function setSitesInformations(array $sitesInformations): void { $this->sitesInformations = $sitesInformations; $this->touch(); }
}

PHP);

w('Project/Domain/Entity/ProjectSite.php', <<<'PHP'
<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ProjectSiteStatus;
use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectSiteRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectSiteRepository::class)]
#[ORM\Table(name: 'project_sites')]
class ProjectSite
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $projectId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $siteId;

    #[ORM\Column(enumType: ProjectSiteStatus::class)]
    private ProjectSiteStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $dateAdded;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $informationsValues = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $employeeIds = [];

    public function __construct(
        Uuid $projectId,
        Uuid $siteId,
        ProjectSiteStatus $status = ProjectSiteStatus::PENDING,
        array $informationsValues = [],
        array $employeeIds = [],
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->projectId = $projectId;
        $this->siteId = $siteId;
        $this->status = $status;
        $this->dateAdded = new \DateTimeImmutable();
        $this->informationsValues = $informationsValues;
        $this->employeeIds = $employeeIds;
    }

    public function getProjectId(): Uuid { return $this->projectId; }
    public function getSiteId(): Uuid { return $this->siteId; }
    public function getStatus(): ProjectSiteStatus { return $this->status; }
    public function getDateAdded(): \DateTimeImmutable { return $this->dateAdded; }
    /** @return array<string, mixed> */
    public function getInformationsValues(): array { return $this->informationsValues; }
    /** @return list<string> */
    public function getEmployeeIds(): array { return $this->employeeIds; }

    public function setStatus(ProjectSiteStatus $status): void { $this->status = $status; $this->touch(); }
    /** @param array<string, mixed> $informationsValues */
    public function setInformationsValues(array $informationsValues): void { $this->informationsValues = $informationsValues; $this->touch(); }
    /** @param list<string> $employeeIds */
    public function setEmployeeIds(array $employeeIds): void { $this->employeeIds = $employeeIds; $this->touch(); }
}

PHP);

w('Project/Domain/Entity/ProjectEvent.php', <<<'PHP'
<?php

namespace App\Project\Domain\Entity;

use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectEventRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectEventRepository::class)]
#[ORM\Table(name: 'project_events')]
class ProjectEvent
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $projectId;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 255)]
    private string $title;

    public function __construct(Uuid $projectId, \DateTimeImmutable $date, string $title)
    {
        $this->initializeUuid();
        $this->projectId = $projectId;
        $this->date = $date;
        $this->title = $title;
    }

    public function getProjectId(): Uuid { return $this->projectId; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getTitle(): string { return $this->title; }
}

PHP);

writeException('Project', 'Project', 'Projet');
writeException('Project', 'ProjectSite', 'Site du projet');

w('Project/Domain/Repository/ProjectRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectStatus;
use Symfony\Component\Uid\Uuid;

interface ProjectRepositoryInterface
{
    public function save(Project $project): void;

    public function findById(Uuid $id): ?Project;

    /** @return list<Project> */
    public function findAllEnabled(?string $search = null): array;

    public function countByClientId(Uuid $clientId): int;

    public function countByStatus(ProjectStatus $status): int;
}

PHP);

w('Project/Domain/Repository/ProjectSiteRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectSite;
use Symfony\Component\Uid\Uuid;

interface ProjectSiteRepositoryInterface
{
    public function save(ProjectSite $projectSite): void;

    public function findById(Uuid $id): ?ProjectSite;

    /** @return list<ProjectSite> */
    public function findByProjectId(Uuid $projectId): array;

    public function findByProjectAndSite(Uuid $projectId, Uuid $siteId): ?ProjectSite;

    public function remove(ProjectSite $projectSite): void;
}

PHP);

w('Project/Domain/Repository/ProjectEventRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectEvent;
use Symfony\Component\Uid\Uuid;

interface ProjectEventRepositoryInterface
{
    public function save(ProjectEvent $event): void;

    /** @return list<ProjectEvent> */
    public function findByProjectId(Uuid $projectId): array;
}

PHP);

require __DIR__ . '/generate-ent-modules-part3b.php';

function generateSimpleCrud(
    string $module,
    string $entity,
    string $routePrefix,
    string $route,
    string $permPrefix,
    array $fields,
    ?string $codeGen = null,
    bool $hasClientId = false,
): void {
    $lower = lcfirst($entity);
    $repoVar = $lower.'Repository';

    w("{$module}/Application/Command/Create{$entity}/Create{$entity}Command.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Command\\Create{$entity};

final readonly class Create{$entity}Command
{
    public function __construct(
        public string \$title,
        public ?string \$description = null,
        public ?string \$clientId = null,
    ) {
    }
}

PHP);

    $codeGenUse = $codeGen ? "use App\\Referentiel\\Application\\Service\\CodeGeneratorService;\nuse App\\Referentiel\\Domain\\Enum\\ReferenceSequenceType;" : '';
    $codeGenCtor = $codeGen ? ",\n        private readonly CodeGeneratorService \$codeGenerator," : '';
    $codeGenBody = $codeGen
        ? "\$code = \$this->codeGenerator->generate(ReferenceSequenceType::{$codeGen});\n        \${$lower} = new {$entity}(\$code, \$title, \$command->description"
        : "\${$lower} = new {$entity}('', \$title, \$command->description";
    $clientIdBody = $hasClientId ? ", \$command->clientId !== null ? Uuid::fromString(\$command->clientId) : null);" : ');';

    if (!$codeGen && !$hasClientId) {
        $codeGenBody = "\${$lower} = new {$entity}(\$title, \$command->description";
        $clientIdBody = ');';
    }

    w("{$module}/Application/Command/Create{$entity}/Create{$entity}Handler.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Command\\Create{$entity};

use App\\{$module}\\Application\\Dto\\{$entity}ResponseDto;
use App\\{$module}\\Domain\\Entity\\{$entity};
use App\\{$module}\\Domain\\Repository\\{$entity}RepositoryInterface;
{$codeGenUse}
use App\\SharedKernel\\Domain\\Validation\\FieldValidator;
use Symfony\\Component\\Uid\\Uuid;

final class Create{$entity}Handler
{
    public function __construct(
        private readonly {$entity}RepositoryInterface \${$repoVar}{$codeGenCtor}
    ) {
    }

    public function handle(Create{$entity}Command \$command): {$entity}ResponseDto
    {
        \$title = FieldValidator::requireNonEmpty(\$command->title, 'Titre');
        {$codeGenBody}{$clientIdBody}
        \$this->{$repoVar}->save(\${$lower});

        return {$entity}ResponseDto::fromEntity(\${$lower});
    }
}

PHP);

    w("{$module}/Application/Command/Update{$entity}/Update{$entity}Command.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Command\\Update{$entity};

final readonly class Update{$entity}Command
{
    public function __construct(
        public string \$id,
        public ?string \$title = null,
        public ?string \$description = null,
        public ?string \$clientId = null,
    ) {
    }
}

PHP);

    $updateClient = $hasClientId ? "        if (\$command->clientId !== null) {\n            \${$lower}->setClientId(Uuid::fromString(\$command->clientId));\n        }\n" : '';

    w("{$module}/Application/Command/Update{$entity}/Update{$entity}Handler.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Command\\Update{$entity};

use App\\{$module}\\Application\\Dto\\{$entity}ResponseDto;
use App\\{$module}\\Domain\\Exception\\{$entity}NotFoundException;
use App\\{$module}\\Domain\\Repository\\{$entity}RepositoryInterface;
use App\\SharedKernel\\Domain\\Validation\\FieldValidator;
use Symfony\\Component\\Uid\\Uuid;

final class Update{$entity}Handler
{
    public function __construct(
        private readonly {$entity}RepositoryInterface \${$repoVar},
    ) {
    }

    public function handle(Update{$entity}Command \$command): {$entity}ResponseDto
    {
        \${$lower} = \$this->{$repoVar}->findById(Uuid::fromString(\$command->id));
        if (null === \${$lower} || !\${$lower}->isEnabled()) {
            throw {$entity}NotFoundException::withId(\$command->id);
        }

        if (\$command->title !== null) {
            \${$lower}->setTitle(FieldValidator::requireNonEmpty(\$command->title, 'Titre'));
        }
        if (\$command->description !== null) {
            \${$lower}->setDescription(\$command->description);
        }
{$updateClient}
        \$this->{$repoVar}->save(\${$lower});

        return {$entity}ResponseDto::fromEntity(\${$lower});
    }
}

PHP);

    w("{$module}/Application/Command/Delete{$entity}/Delete{$entity}Command.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Command\\Delete{$entity};

final readonly class Delete{$entity}Command
{
    public function __construct(public string \$id) {}
}

PHP);

    w("{$module}/Application/Command/Delete{$entity}/Delete{$entity}Handler.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Command\\Delete{$entity};

use App\\{$module}\\Application\\Dto\\{$entity}ResponseDto;
use App\\{$module}\\Domain\\Exception\\{$entity}NotFoundException;
use App\\{$module}\\Domain\\Repository\\{$entity}RepositoryInterface;
use Symfony\\Component\\Uid\\Uuid;

final class Delete{$entity}Handler
{
    public function __construct(
        private readonly {$entity}RepositoryInterface \${$repoVar},
    ) {
    }

    public function handle(Delete{$entity}Command \$command): {$entity}ResponseDto
    {
        \${$lower} = \$this->{$repoVar}->findById(Uuid::fromString(\$command->id));
        if (null === \${$lower} || !\${$lower}->isEnabled()) {
            throw {$entity}NotFoundException::withId(\$command->id);
        }

        \${$lower}->disable();
        \$this->{$repoVar}->save(\${$lower});

        return {$entity}ResponseDto::fromEntity(\${$lower});
    }
}

PHP);

    w("{$module}/Application/Query/List{$entity}s/List{$entity}sQuery.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Query\\List{$entity}s;

final readonly class List{$entity}sQuery
{
    public function __construct(public ?string \$search = null) {}
}

PHP);

    w("{$module}/Application/Query/List{$entity}s/List{$entity}sHandler.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Query\\List{$entity}s;

use App\\{$module}\\Application\\Dto\\{$entity}ResponseDto;
use App\\{$module}\\Domain\\Repository\\{$entity}RepositoryInterface;

final class List{$entity}sHandler
{
    public function __construct(
        private readonly {$entity}RepositoryInterface \${$repoVar},
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(List{$entity}sQuery \$query): array
    {
        return array_map(
            static fn (\$item) => {$entity}ResponseDto::fromEntity(\$item)->toArray(),
            \$this->{$repoVar}->findAllEnabled(\$query->search),
        );
    }
}

PHP);

    w("{$module}/Application/Query/Get{$entity}/Get{$entity}Query.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Query\\Get{$entity};

final readonly class Get{$entity}Query
{
    public function __construct(public string \$id) {}
}

PHP);

    w("{$module}/Application/Query/Get{$entity}/Get{$entity}Handler.php", <<<PHP
<?php

namespace App\\{$module}\\Application\\Query\\Get{$entity};

use App\\{$module}\\Application\\Dto\\{$entity}ResponseDto;
use App\\{$module}\\Domain\\Exception\\{$entity}NotFoundException;
use App\\{$module}\\Domain\\Repository\\{$entity}RepositoryInterface;
use Symfony\\Component\\Uid\\Uuid;

final class Get{$entity}Handler
{
    public function __construct(
        private readonly {$entity}RepositoryInterface \${$repoVar},
    ) {
    }

    public function handle(Get{$entity}Query \$query): {$entity}ResponseDto
    {
        \${$lower} = \$this->{$repoVar}->findById(Uuid::fromString(\$query->id));
        if (null === \${$lower} || !\${$lower}->isEnabled()) {
            throw {$entity}NotFoundException::withId(\$query->id);
        }

        return {$entity}ResponseDto::fromEntity(\${$lower});
    }
}

PHP);

    w("{$module}/Presentation/Api/Controller/{$entity}Controller.php", <<<PHP
<?php

namespace App\\{$module}\\Presentation\\Api\\Controller;

use App\\{$module}\\Application\\Command\\Create{$entity}\\Create{$entity}Command;
use App\\{$module}\\Application\\Command\\Create{$entity}\\Create{$entity}Handler;
use App\\{$module}\\Application\\Command\\Delete{$entity}\\Delete{$entity}Command;
use App\\{$module}\\Application\\Command\\Delete{$entity}\\Delete{$entity}Handler;
use App\\{$module}\\Application\\Command\\Update{$entity}\\Update{$entity}Command;
use App\\{$module}\\Application\\Command\\Update{$entity}\\Update{$entity}Handler;
use App\\{$module}\\Application\\Query\\Get{$entity}\\Get{$entity}Handler;
use App\\{$module}\\Application\\Query\\Get{$entity}\\Get{$entity}Query;
use App\\{$module}\\Application\\Query\\List{$entity}s\\List{$entity}sHandler;
use App\\{$module}\\Application\\Query\\List{$entity}s\\List{$entity}sQuery;
use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;
use Symfony\\Component\\HttpFoundation\\JsonResponse;
use Symfony\\Component\\HttpFoundation\\Request;
use Symfony\\Component\\HttpFoundation\\Response;
use Symfony\\Component\\Routing\\Attribute\\Route;
use Symfony\\Component\\Security\\Http\\Attribute\\IsGranted;

#[Route('{$route}')]
final class {$entity}Controller extends AbstractController
{
    #[Route('', name: 'api_{$routePrefix}_list', methods: ['GET'])]
    #[IsGranted('{$permPrefix}.view')]
    public function list(Request \$request, List{$entity}sHandler \$handler): JsonResponse
    {
        return \$this->json(\$handler->handle(new List{$entity}sQuery(\$request->query->get('search'))));
    }

    #[Route('', name: 'api_{$routePrefix}_create', methods: ['POST'])]
    #[IsGranted('{$permPrefix}.create')]
    public function create(Request \$request, Create{$entity}Handler \$handler): JsonResponse
    {
        \$data = \$request->toArray();
        \$result = \$handler->handle(new Create{$entity}Command(
            title: \$data['title'] ?? '',
            description: \$data['description'] ?? null,
            clientId: \$data['clientId'] ?? null,
        ));

        return \$this->json(\$result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_{$routePrefix}_get', methods: ['GET'])]
    #[IsGranted('{$permPrefix}.view')]
    public function get(string \$id, Get{$entity}Handler \$handler): JsonResponse
    {
        return \$this->json(\$handler->handle(new Get{$entity}Query(\$id))->toArray());
    }

    #[Route('/{id}', name: 'api_{$routePrefix}_update', methods: ['PUT'])]
    #[IsGranted('{$permPrefix}.update')]
    public function update(string \$id, Request \$request, Update{$entity}Handler \$handler): JsonResponse
    {
        \$data = \$request->toArray();
        \$result = \$handler->handle(new Update{$entity}Command(
            id: \$id,
            title: \$data['title'] ?? null,
            description: array_key_exists('description', \$data) ? \$data['description'] : null,
            clientId: \$data['clientId'] ?? null,
        ));

        return \$this->json(\$result->toArray());
    }

    #[Route('/{id}', name: 'api_{$routePrefix}_delete', methods: ['DELETE'])]
    #[IsGranted('{$permPrefix}.delete')]
    public function delete(string \$id, Delete{$entity}Handler \$handler): JsonResponse
    {
        return \$this->json(\$handler->handle(new Delete{$entity}Command(\$id))->toArray());
    }
}

PHP);
}
