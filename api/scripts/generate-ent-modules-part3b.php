<?php

declare(strict_types=1);

// Project repositories
w('Project/Infrastructure/Persistence/Doctrine/DoctrineProjectRepository.php', <<<'PHP'
<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Project> */
class DoctrineProjectRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function save(Project $project): void
    {
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Project
    {
        return $this->find($id);
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('p.title LIKE :search OR p.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countByClientId(Uuid $clientId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.clientId = :clientId')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('clientId', $clientId, 'uuid')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(ProjectStatus $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('status', $status)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

PHP);

w('Project/Infrastructure/Persistence/Doctrine/DoctrineProjectSiteRepository.php', <<<'PHP'
<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectSite;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ProjectSite> */
class DoctrineProjectSiteRepository extends ServiceEntityRepository implements ProjectSiteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectSite::class);
    }

    public function save(ProjectSite $projectSite): void
    {
        $this->getEntityManager()->persist($projectSite);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?ProjectSite
    {
        return $this->find($id);
    }

    public function findByProjectId(Uuid $projectId): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->orderBy('ps.dateAdded', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProjectAndSite(Uuid $projectId, Uuid $siteId): ?ProjectSite
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.projectId = :projectId')
            ->andWhere('ps.siteId = :siteId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->setParameter('siteId', $siteId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function remove(ProjectSite $projectSite): void
    {
        $this->getEntityManager()->remove($projectSite);
        $this->getEntityManager()->flush();
    }
}

PHP);

w('Project/Infrastructure/Persistence/Doctrine/DoctrineProjectEventRepository.php', <<<'PHP'
<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectEvent;
use App\Project\Domain\Repository\ProjectEventRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ProjectEvent> */
class DoctrineProjectEventRepository extends ServiceEntityRepository implements ProjectEventRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectEvent::class);
    }

    public function save(ProjectEvent $event): void
    {
        $this->getEntityManager()->persist($event);
        $this->getEntityManager()->flush();
    }

    public function findByProjectId(Uuid $projectId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Project/Application/Dto/ProjectResponseDto.php', <<<'PHP'
<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\Project;

final readonly class ProjectResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $object,
        public ?string $dateDebut,
        public ?string $dateFin,
        public string $status,
        public float $budget,
        public string $clientId,
        public array $sitesInformations,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Project $project): self
    {
        return new self(
            id: (string) $project->getId(),
            code: $project->getCode(),
            title: $project->getTitle(),
            object: $project->getObject(),
            dateDebut: $project->getDateDebut()?->format('Y-m-d'),
            dateFin: $project->getDateFin()?->format('Y-m-d'),
            status: $project->getStatus()->value,
            budget: $project->getBudget(),
            clientId: (string) $project->getClientId(),
            sitesInformations: $project->getSitesInformations(),
            isEnabled: $project->isEnabled(),
            createdAt: $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $project->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'object' => $this->object,
            'dateDebut' => $this->dateDebut,
            'dateFin' => $this->dateFin,
            'status' => $this->status,
            'budget' => $this->budget,
            'clientId' => $this->clientId,
            'sitesInformations' => $this->sitesInformations,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Project/Application/Dto/ProjectSiteResponseDto.php', <<<'PHP'
<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\ProjectSite;

final readonly class ProjectSiteResponseDto
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $siteId,
        public string $status,
        public string $dateAdded,
        public array $informationsValues,
        public array $employeeIds,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(ProjectSite $ps): self
    {
        return new self(
            id: (string) $ps->getId(),
            projectId: (string) $ps->getProjectId(),
            siteId: (string) $ps->getSiteId(),
            status: $ps->getStatus()->value,
            dateAdded: $ps->getDateAdded()->format(\DateTimeInterface::ATOM),
            informationsValues: $ps->getInformationsValues(),
            employeeIds: $ps->getEmployeeIds(),
            createdAt: $ps->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $ps->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->projectId,
            'siteId' => $this->siteId,
            'status' => $this->status,
            'dateAdded' => $this->dateAdded,
            'informationsValues' => $this->informationsValues,
            'employeeIds' => $this->employeeIds,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Project/Application/Dto/ProjectEventResponseDto.php', <<<'PHP'
<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\ProjectEvent;

final readonly class ProjectEventResponseDto
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $date,
        public string $title,
    ) {
    }

    public static function fromEntity(ProjectEvent $event): self
    {
        return new self(
            id: (string) $event->getId(),
            projectId: (string) $event->getProjectId(),
            date: $event->getDate()->format('Y-m-d'),
            title: $event->getTitle(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->projectId,
            'date' => $this->date,
            'title' => $this->title,
        ];
    }
}

PHP);

w('Project/Application/Dto/ProjectDetailResponseDto.php', <<<'PHP'
<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\Project;

final readonly class ProjectDetailResponseDto
{
    /** @param list<array<string, mixed>> $sites */
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $object,
        public ?string $dateDebut,
        public ?string $dateFin,
        public string $status,
        public float $budget,
        public string $clientId,
        public ?string $clientTitle,
        public array $sitesInformations,
        public array $sites,
        public array $events,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'object' => $this->object,
            'dateDebut' => $this->dateDebut,
            'dateFin' => $this->dateFin,
            'status' => $this->status,
            'budget' => $this->budget,
            'clientId' => $this->clientId,
            'clientTitle' => $this->clientTitle,
            'sitesInformations' => $this->sitesInformations,
            'sites' => $this->sites,
            'events' => $this->events,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part3c.php';
