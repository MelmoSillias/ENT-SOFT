<?php

declare(strict_types=1);

// Project commands & queries
w('Project/Application/Command/CreateProject/CreateProjectCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\CreateProject;

final readonly class CreateProjectCommand
{
    public function __construct(
        public string $title,
        public string $clientId,
        public ?string $object = null,
        public ?string $dateDebut = null,
        public ?string $dateFin = null,
        public string $status = 'draft',
        public float $budget = 0.0,
        public array $sitesInformations = [],
    ) {
    }
}

PHP);

w('Project/Application/Command/CreateProject/CreateProjectHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\CreateProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateProjectCommand $command): ProjectResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::PROJECT);
        $project = new Project(
            code: $code,
            title: $title,
            clientId: Uuid::fromString($command->clientId),
            status: ProjectStatus::from($command->status),
            object: $command->object,
            dateDebut: $command->dateDebut ? new \DateTimeImmutable($command->dateDebut) : null,
            dateFin: $command->dateFin ? new \DateTimeImmutable($command->dateFin) : null,
            budget: $command->budget,
            sitesInformations: $command->sitesInformations,
        );
        $this->projectRepository->save($project);

        return ProjectResponseDto::fromEntity($project);
    }
}

PHP);

w('Project/Application/Command/UpdateProject/UpdateProjectCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\UpdateProject;

final readonly class UpdateProjectCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $object = null,
        public ?string $dateDebut = null,
        public ?string $dateFin = null,
        public ?string $status = null,
        public ?float $budget = null,
        public ?string $clientId = null,
        public ?array $sitesInformations = null,
    ) {
    }
}

PHP);

w('Project/Application/Command/UpdateProject/UpdateProjectHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\UpdateProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(UpdateProjectCommand $command): ProjectResponseDto
    {
        $project = $this->projectRepository->findById(Uuid::fromString($command->id));
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $project->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->object !== null) {
            $project->setObject($command->object);
        }
        if ($command->dateDebut !== null) {
            $project->setDateDebut(new \DateTimeImmutable($command->dateDebut));
        }
        if ($command->dateFin !== null) {
            $project->setDateFin(new \DateTimeImmutable($command->dateFin));
        }
        if ($command->status !== null) {
            $project->setStatus(ProjectStatus::from($command->status));
        }
        if ($command->budget !== null) {
            $project->setBudget($command->budget);
        }
        if ($command->clientId !== null) {
            $project->setClientId(Uuid::fromString($command->clientId));
        }
        if ($command->sitesInformations !== null) {
            $project->setSitesInformations($command->sitesInformations);
        }

        $this->projectRepository->save($project);

        return ProjectResponseDto::fromEntity($project);
    }
}

PHP);

w('Project/Application/Command/DeleteProject/DeleteProjectCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\DeleteProject;

final readonly class DeleteProjectCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Project/Application/Command/DeleteProject/DeleteProjectHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\DeleteProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(DeleteProjectCommand $command): ProjectResponseDto
    {
        $project = $this->projectRepository->findById(Uuid::fromString($command->id));
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->id);
        }

        $project->disable();
        $this->projectRepository->save($project);

        return ProjectResponseDto::fromEntity($project);
    }
}

PHP);

w('Project/Application/Command/AddSiteToProject/AddSiteToProjectCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\AddSiteToProject;

final readonly class AddSiteToProjectCommand
{
    public function __construct(
        public string $projectId,
        public string $siteId,
        public string $status = 'pending',
        public array $informationsValues = [],
        public array $employeeIds = [],
    ) {
    }
}

PHP);

w('Project/Application/Command/AddSiteToProject/AddSiteToProjectHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\AddSiteToProject;

use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Domain\Entity\ProjectSite;
use App\Project\Domain\Enum\ProjectSiteStatus;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class AddSiteToProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    public function handle(AddSiteToProjectCommand $command): ProjectSiteResponseDto
    {
        $projectId = Uuid::fromString($command->projectId);
        $project = $this->projectRepository->findById($projectId);
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->projectId);
        }

        $siteId = Uuid::fromString($command->siteId);
        if ($this->projectSiteRepository->findByProjectAndSite($projectId, $siteId) !== null) {
            throw new \InvalidArgumentException('Ce site est déjà associé au projet.');
        }

        $projectSite = new ProjectSite(
            projectId: $projectId,
            siteId: $siteId,
            status: ProjectSiteStatus::from($command->status),
            informationsValues: $command->informationsValues,
            employeeIds: $command->employeeIds,
        );
        $this->projectSiteRepository->save($projectSite);

        return ProjectSiteResponseDto::fromEntity($projectSite);
    }
}

PHP);

w('Project/Application/Command/UpdateProjectSite/UpdateProjectSiteCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\UpdateProjectSite;

final readonly class UpdateProjectSiteCommand
{
    public function __construct(
        public string $id,
        public ?string $status = null,
        public ?array $informationsValues = null,
        public ?array $employeeIds = null,
    ) {
    }
}

PHP);

w('Project/Application/Command/UpdateProjectSite/UpdateProjectSiteHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\UpdateProjectSite;

use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Domain\Enum\ProjectSiteStatus;
use App\Project\Domain\Exception\ProjectSiteNotFoundException;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateProjectSiteHandler
{
    public function __construct(
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    public function handle(UpdateProjectSiteCommand $command): ProjectSiteResponseDto
    {
        $projectSite = $this->projectSiteRepository->findById(Uuid::fromString($command->id));
        if (null === $projectSite) {
            throw ProjectSiteNotFoundException::withId($command->id);
        }

        if ($command->status !== null) {
            $projectSite->setStatus(ProjectSiteStatus::from($command->status));
        }
        if ($command->informationsValues !== null) {
            $projectSite->setInformationsValues($command->informationsValues);
        }
        if ($command->employeeIds !== null) {
            $projectSite->setEmployeeIds($command->employeeIds);
        }

        $this->projectSiteRepository->save($projectSite);

        return ProjectSiteResponseDto::fromEntity($projectSite);
    }
}

PHP);

w('Project/Application/Command/RemoveSiteFromProject/RemoveSiteFromProjectCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\RemoveSiteFromProject;

final readonly class RemoveSiteFromProjectCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Project/Application/Command/RemoveSiteFromProject/RemoveSiteFromProjectHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\RemoveSiteFromProject;

use App\Project\Domain\Exception\ProjectSiteNotFoundException;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class RemoveSiteFromProjectHandler
{
    public function __construct(
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    public function handle(RemoveSiteFromProjectCommand $command): void
    {
        $projectSite = $this->projectSiteRepository->findById(Uuid::fromString($command->id));
        if (null === $projectSite) {
            throw ProjectSiteNotFoundException::withId($command->id);
        }

        $this->projectSiteRepository->remove($projectSite);
    }
}

PHP);

w('Project/Application/Command/CreateProjectEvent/CreateProjectEventCommand.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\CreateProjectEvent;

final readonly class CreateProjectEventCommand
{
    public function __construct(
        public string $projectId,
        public string $date,
        public string $title,
    ) {
    }
}

PHP);

w('Project/Application/Command/CreateProjectEvent/CreateProjectEventHandler.php', <<<'PHP'
<?php

namespace App\Project\Application\Command\CreateProjectEvent;

use App\Project\Application\Dto\ProjectEventResponseDto;
use App\Project\Domain\Entity\ProjectEvent;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectEventRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateProjectEventHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectEventRepositoryInterface $projectEventRepository,
    ) {
    }

    public function handle(CreateProjectEventCommand $command): ProjectEventResponseDto
    {
        $projectId = Uuid::fromString($command->projectId);
        $project = $this->projectRepository->findById($projectId);
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->projectId);
        }

        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $event = new ProjectEvent($projectId, new \DateTimeImmutable($command->date), $title);
        $this->projectEventRepository->save($event);

        return ProjectEventResponseDto::fromEntity($event);
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part3d.php';
