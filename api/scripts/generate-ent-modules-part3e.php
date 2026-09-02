<?php

declare(strict_types=1);

// Employee CRUD handlers & controller
w('Employee/Application/Command/CreateEmployee/CreateEmployeeCommand.php', <<<'PHP'
<?php

namespace App\Employee\Application\Command\CreateEmployee;

final readonly class CreateEmployeeCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $function,
        public ?string $address = null,
        public ?string $userId = null,
    ) {
    }
}

PHP);

w('Employee/Application/Command/CreateEmployee/CreateEmployeeHandler.php', <<<'PHP'
<?php

namespace App\Employee\Application\Command\CreateEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(CreateEmployeeCommand $command): EmployeeResponseDto
    {
        $name = FieldValidator::requireNonEmpty($command->name, 'Nom');
        $email = FieldValidator::requireNonEmpty($command->email, 'Email');
        $phone = FieldValidator::requirePhone($command->phone);
        $function = FieldValidator::requireNonEmpty($command->function, 'Fonction');

        $employee = new Employee(
            name: $name,
            email: $email,
            phone: $phone,
            function: $function,
            address: $command->address,
            userId: $command->userId ? Uuid::fromString($command->userId) : null,
        );
        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}

PHP);

w('Employee/Application/Command/UpdateEmployee/UpdateEmployeeCommand.php', <<<'PHP'
<?php

namespace App\Employee\Application\Command\UpdateEmployee;

final readonly class UpdateEmployeeCommand
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $function = null,
        public ?string $address = null,
        public ?string $userId = null,
    ) {
    }
}

PHP);

w('Employee/Application/Command/UpdateEmployee/UpdateEmployeeHandler.php', <<<'PHP'
<?php

namespace App\Employee\Application\Command\UpdateEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(UpdateEmployeeCommand $command): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($command->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($command->id);
        }

        if ($command->name !== null) {
            $employee->setName(FieldValidator::requireNonEmpty($command->name, 'Nom'));
        }
        if ($command->email !== null) {
            $employee->setEmail(FieldValidator::requireNonEmpty($command->email, 'Email'));
        }
        if ($command->phone !== null) {
            $employee->setPhone(FieldValidator::requirePhone($command->phone));
        }
        if ($command->function !== null) {
            $employee->setFunction(FieldValidator::requireNonEmpty($command->function, 'Fonction'));
        }
        if ($command->address !== null) {
            $employee->setAddress($command->address);
        }
        if ($command->userId !== null) {
            $employee->setUserId($command->userId !== '' ? Uuid::fromString($command->userId) : null);
        }

        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}

PHP);

w('Employee/Application/Command/DeleteEmployee/DeleteEmployeeCommand.php', <<<'PHP'
<?php

namespace App\Employee\Application\Command\DeleteEmployee;

final readonly class DeleteEmployeeCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Employee/Application/Command/DeleteEmployee/DeleteEmployeeHandler.php', <<<'PHP'
<?php

namespace App\Employee\Application\Command\DeleteEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(DeleteEmployeeCommand $command): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($command->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($command->id);
        }

        $employee->disable();
        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }
}

PHP);

w('Employee/Application/Query/ListEmployees/ListEmployeesQuery.php', <<<'PHP'
<?php

namespace App\Employee\Application\Query\ListEmployees;

final readonly class ListEmployeesQuery
{
    public function __construct(public ?string $search = null) {}
}

PHP);

w('Employee/Application/Query/ListEmployees/ListEmployeesHandler.php', <<<'PHP'
<?php

namespace App\Employee\Application\Query\ListEmployees;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;

final class ListEmployeesHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListEmployeesQuery $query): array
    {
        return array_map(
            static fn ($e) => EmployeeResponseDto::fromEntity($e)->toArray(),
            $this->employeeRepository->findAllEnabled($query->search),
        );
    }
}

PHP);

w('Employee/Application/Query/GetEmployee/GetEmployeeQuery.php', <<<'PHP'
<?php

namespace App\Employee\Application\Query\GetEmployee;

final readonly class GetEmployeeQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Employee/Application/Query/GetEmployee/GetEmployeeHandler.php', <<<'PHP'
<?php

namespace App\Employee\Application\Query\GetEmployee;

use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Exception\EmployeeNotFoundException;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {
    }

    public function handle(GetEmployeeQuery $query): EmployeeResponseDto
    {
        $employee = $this->employeeRepository->findById(Uuid::fromString($query->id));
        if (null === $employee || !$employee->isEnabled()) {
            throw EmployeeNotFoundException::withId($query->id);
        }

        return EmployeeResponseDto::fromEntity($employee);
    }
}

PHP);

w('Employee/Presentation/Api/Controller/EmployeeController.php', <<<'PHP'
<?php

namespace App\Employee\Presentation\Api\Controller;

use App\Employee\Application\Command\CreateEmployee\CreateEmployeeCommand;
use App\Employee\Application\Command\CreateEmployee\CreateEmployeeHandler;
use App\Employee\Application\Command\DeleteEmployee\DeleteEmployeeCommand;
use App\Employee\Application\Command\DeleteEmployee\DeleteEmployeeHandler;
use App\Employee\Application\Command\UpdateEmployee\UpdateEmployeeCommand;
use App\Employee\Application\Command\UpdateEmployee\UpdateEmployeeHandler;
use App\Employee\Application\Query\GetEmployee\GetEmployeeHandler;
use App\Employee\Application\Query\GetEmployee\GetEmployeeQuery;
use App\Employee\Application\Query\ListEmployees\ListEmployeesHandler;
use App\Employee\Application\Query\ListEmployees\ListEmployeesQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employees')]
final class EmployeeController extends AbstractController
{
    #[Route('', name: 'api_employees_list', methods: ['GET'])]
    #[IsGranted('employee.employees.view')]
    public function list(Request $request, ListEmployeesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListEmployeesQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_employees_create', methods: ['POST'])]
    #[IsGranted('employee.employees.create')]
    public function create(Request $request, CreateEmployeeHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateEmployeeCommand(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? '',
            function: $data['function'] ?? '',
            address: $data['address'] ?? null,
            userId: $data['userId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_employees_get', methods: ['GET'])]
    #[IsGranted('employee.employees.view')]
    public function get(string $id, GetEmployeeHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetEmployeeQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_employees_update', methods: ['PUT'])]
    #[IsGranted('employee.employees.update')]
    public function update(string $id, Request $request, UpdateEmployeeHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateEmployeeCommand(
            id: $id,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            function: $data['function'] ?? null,
            address: $data['address'] ?? null,
            userId: $data['userId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_employees_delete', methods: ['DELETE'])]
    #[IsGranted('employee.employees.delete')]
    public function delete(string $id, DeleteEmployeeHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteEmployeeCommand($id))->toArray());
    }
}

PHP);

// ========== TASK MODULE ==========

w('Task/Domain/Enum/TaskStatus.php', <<<'PHP'
<?php

namespace App\Task\Domain\Enum;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

PHP);

w('Task/Domain/Entity/Task.php', <<<'PHP'
<?php

namespace App\Task\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Infrastructure\Persistence\Doctrine\DoctrineTaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineTaskRepository::class)]
#[ORM\Table(name: 'tasks')]
class Task
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDue;

    #[ORM\Column(enumType: TaskStatus::class)]
    private TaskStatus $status;

    #[ORM\Column(type: 'uuid')]
    private Uuid $siteId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $employeeId;

    public function __construct(
        string $title,
        Uuid $siteId,
        TaskStatus $status = TaskStatus::PENDING,
        ?string $description = null,
        ?\DateTimeImmutable $dateDue = null,
        ?Uuid $employeeId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->title = $title;
        $this->siteId = $siteId;
        $this->status = $status;
        $this->description = $description;
        $this->dateCreation = new \DateTimeImmutable();
        $this->dateDue = $dateDue;
        $this->employeeId = $employeeId;
    }

    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }
    public function getDateDue(): ?\DateTimeImmutable { return $this->dateDue; }
    public function getStatus(): TaskStatus { return $this->status; }
    public function getSiteId(): Uuid { return $this->siteId; }
    public function getEmployeeId(): ?Uuid { return $this->employeeId; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setDateDue(?\DateTimeImmutable $dateDue): void { $this->dateDue = $dateDue; $this->touch(); }
    public function setStatus(TaskStatus $status): void { $this->status = $status; $this->touch(); }
    public function setSiteId(Uuid $siteId): void { $this->siteId = $siteId; $this->touch(); }
    public function setEmployeeId(?Uuid $employeeId): void { $this->employeeId = $employeeId; $this->touch(); }
}

PHP);

writeException('Task', 'Task', 'Tâche');

w('Task/Domain/Repository/TaskRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Task\Domain\Repository;

use App\Task\Domain\Entity\Task;
use App\Task\Domain\Enum\TaskStatus;
use Symfony\Component\Uid\Uuid;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(Uuid $id): ?Task;

    /** @return list<Task> */
    public function findFiltered(
        ?Uuid $siteId = null,
        ?Uuid $employeeId = null,
        ?TaskStatus $status = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array;

    public function countDueToday(): int;
}

PHP);

require __DIR__ . '/generate-ent-modules-part4.php';
