<?php

declare(strict_types=1);

// Task infrastructure & application
w('Task/Infrastructure/Persistence/Doctrine/DoctrineTaskRepository.php', <<<'PHP'
<?php

namespace App\Task\Infrastructure\Persistence\Doctrine;

use App\Task\Domain\Entity\Task;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Task> */
class DoctrineTaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function save(Task $task): void
    {
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Task
    {
        return $this->find($id);
    }

    public function findFiltered(
        ?Uuid $siteId = null,
        ?Uuid $employeeId = null,
        ?TaskStatus $status = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.dateDue', 'ASC');

        if ($siteId !== null) {
            $qb->andWhere('t.siteId = :siteId')->setParameter('siteId', $siteId, 'uuid');
        }
        if ($employeeId !== null) {
            $qb->andWhere('t.employeeId = :employeeId')->setParameter('employeeId', $employeeId, 'uuid');
        }
        if ($status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        if ($from !== null) {
            $qb->andWhere('t.dateDue >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('t.dateDue <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function countDueToday(): int
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.dateDue >= :today')
            ->andWhere('t.dateDue < :tomorrow')
            ->andWhere('t.status != :cancelled')
            ->setParameter('enabled', true)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('cancelled', TaskStatus::CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

PHP);

w('Task/Application/Dto/TaskResponseDto.php', <<<'PHP'
<?php

namespace App\Task\Application\Dto;

use App\Task\Domain\Entity\Task;

final readonly class TaskResponseDto
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $dateCreation,
        public ?string $dateDue,
        public string $status,
        public string $siteId,
        public ?string $employeeId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Task $task): self
    {
        return new self(
            id: (string) $task->getId(),
            title: $task->getTitle(),
            description: $task->getDescription(),
            dateCreation: $task->getDateCreation()->format(\DateTimeInterface::ATOM),
            dateDue: $task->getDateDue()?->format('Y-m-d'),
            status: $task->getStatus()->value,
            siteId: (string) $task->getSiteId(),
            employeeId: $task->getEmployeeId()?->toRfc4122(),
            isEnabled: $task->isEnabled(),
            createdAt: $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'dateCreation' => $this->dateCreation,
            'dateDue' => $this->dateDue,
            'status' => $this->status,
            'siteId' => $this->siteId,
            'employeeId' => $this->employeeId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Task/Application/Command/CreateTask/CreateTaskCommand.php', <<<'PHP'
<?php

namespace App\Task\Application\Command\CreateTask;

final readonly class CreateTaskCommand
{
    public function __construct(
        public string $title,
        public string $siteId,
        public string $status = 'pending',
        public ?string $description = null,
        public ?string $dateDue = null,
        public ?string $employeeId = null,
    ) {
    }
}

PHP);

w('Task/Application/Command/CreateTask/CreateTaskHandler.php', <<<'PHP'
<?php

namespace App\Task\Application\Command\CreateTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Entity\Task;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(CreateTaskCommand $command): TaskResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $task = new Task(
            title: $title,
            siteId: Uuid::fromString($command->siteId),
            status: TaskStatus::from($command->status),
            description: $command->description,
            dateDue: $command->dateDue ? new \DateTimeImmutable($command->dateDue) : null,
            employeeId: $command->employeeId ? Uuid::fromString($command->employeeId) : null,
        );
        $this->taskRepository->save($task);

        return TaskResponseDto::fromEntity($task);
    }
}

PHP);

w('Task/Application/Command/UpdateTask/UpdateTaskCommand.php', <<<'PHP'
<?php

namespace App\Task\Application\Command\UpdateTask;

final readonly class UpdateTaskCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $dateDue = null,
        public ?string $status = null,
        public ?string $siteId = null,
        public ?string $employeeId = null,
    ) {
    }
}

PHP);

w('Task/Application/Command/UpdateTask/UpdateTaskHandler.php', <<<'PHP'
<?php

namespace App\Task\Application\Command\UpdateTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Exception\TaskNotFoundException;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(UpdateTaskCommand $command): TaskResponseDto
    {
        $task = $this->taskRepository->findById(Uuid::fromString($command->id));
        if (null === $task || !$task->isEnabled()) {
            throw TaskNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $task->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->description !== null) {
            $task->setDescription($command->description);
        }
        if ($command->dateDue !== null) {
            $task->setDateDue(new \DateTimeImmutable($command->dateDue));
        }
        if ($command->status !== null) {
            $task->setStatus(TaskStatus::from($command->status));
        }
        if ($command->siteId !== null) {
            $task->setSiteId(Uuid::fromString($command->siteId));
        }
        if ($command->employeeId !== null) {
            $task->setEmployeeId($command->employeeId !== '' ? Uuid::fromString($command->employeeId) : null);
        }

        $this->taskRepository->save($task);

        return TaskResponseDto::fromEntity($task);
    }
}

PHP);

w('Task/Application/Command/DeleteTask/DeleteTaskCommand.php', <<<'PHP'
<?php

namespace App\Task\Application\Command\DeleteTask;

final readonly class DeleteTaskCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Task/Application/Command/DeleteTask/DeleteTaskHandler.php', <<<'PHP'
<?php

namespace App\Task\Application\Command\DeleteTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Exception\TaskNotFoundException;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(DeleteTaskCommand $command): TaskResponseDto
    {
        $task = $this->taskRepository->findById(Uuid::fromString($command->id));
        if (null === $task || !$task->isEnabled()) {
            throw TaskNotFoundException::withId($command->id);
        }

        $task->disable();
        $this->taskRepository->save($task);

        return TaskResponseDto::fromEntity($task);
    }
}

PHP);

w('Task/Application/Query/ListTasks/ListTasksQuery.php', <<<'PHP'
<?php

namespace App\Task\Application\Query\ListTasks;

final readonly class ListTasksQuery
{
    public function __construct(
        public ?string $siteId = null,
        public ?string $employeeId = null,
        public ?string $status = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}

PHP);

w('Task/Application/Query/ListTasks/ListTasksHandler.php', <<<'PHP'
<?php

namespace App\Task\Application\Query\ListTasks;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListTasksHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListTasksQuery $query): array
    {
        $tasks = $this->taskRepository->findFiltered(
            siteId: $query->siteId ? Uuid::fromString($query->siteId) : null,
            employeeId: $query->employeeId ? Uuid::fromString($query->employeeId) : null,
            status: $query->status ? TaskStatus::from($query->status) : null,
            from: $query->from ? new \DateTimeImmutable($query->from) : null,
            to: $query->to ? new \DateTimeImmutable($query->to) : null,
        );

        return array_map(
            static fn ($t) => TaskResponseDto::fromEntity($t)->toArray(),
            $tasks,
        );
    }
}

PHP);

w('Task/Application/Query/GetTask/GetTaskQuery.php', <<<'PHP'
<?php

namespace App\Task\Application\Query\GetTask;

final readonly class GetTaskQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Task/Application/Query/GetTask/GetTaskHandler.php', <<<'PHP'
<?php

namespace App\Task\Application\Query\GetTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Exception\TaskNotFoundException;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(GetTaskQuery $query): TaskResponseDto
    {
        $task = $this->taskRepository->findById(Uuid::fromString($query->id));
        if (null === $task || !$task->isEnabled()) {
            throw TaskNotFoundException::withId($query->id);
        }

        return TaskResponseDto::fromEntity($task);
    }
}

PHP);

w('Task/Presentation/Api/Controller/TaskController.php', <<<'PHP'
<?php

namespace App\Task\Presentation\Api\Controller;

use App\Task\Application\Command\CreateTask\CreateTaskCommand;
use App\Task\Application\Command\CreateTask\CreateTaskHandler;
use App\Task\Application\Command\DeleteTask\DeleteTaskCommand;
use App\Task\Application\Command\DeleteTask\DeleteTaskHandler;
use App\Task\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Task\Application\Command\UpdateTask\UpdateTaskHandler;
use App\Task\Application\Query\GetTask\GetTaskHandler;
use App\Task\Application\Query\GetTask\GetTaskQuery;
use App\Task\Application\Query\ListTasks\ListTasksHandler;
use App\Task\Application\Query\ListTasks\ListTasksQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tasks')]
final class TaskController extends AbstractController
{
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    #[IsGranted('task.tasks.view')]
    public function list(Request $request, ListTasksHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListTasksQuery(
            siteId: $request->query->get('siteId'),
            employeeId: $request->query->get('employeeId'),
            status: $request->query->get('status'),
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        )));
    }

    #[Route('', name: 'api_tasks_create', methods: ['POST'])]
    #[IsGranted('task.tasks.create')]
    public function create(Request $request, CreateTaskHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateTaskCommand(
            title: $data['title'] ?? '',
            siteId: $data['siteId'] ?? '',
            status: $data['status'] ?? 'pending',
            description: $data['description'] ?? null,
            dateDue: $data['dateDue'] ?? null,
            employeeId: $data['employeeId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_tasks_get', methods: ['GET'])]
    #[IsGranted('task.tasks.view')]
    public function get(string $id, GetTaskHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetTaskQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_tasks_update', methods: ['PUT'])]
    #[IsGranted('task.tasks.update')]
    public function update(string $id, Request $request, UpdateTaskHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateTaskCommand(
            id: $id,
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            dateDue: $data['dateDue'] ?? null,
            status: $data['status'] ?? null,
            siteId: $data['siteId'] ?? null,
            employeeId: $data['employeeId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_tasks_delete', methods: ['DELETE'])]
    #[IsGranted('task.tasks.delete')]
    public function delete(string $id, DeleteTaskHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteTaskCommand($id))->toArray());
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part5.php';
