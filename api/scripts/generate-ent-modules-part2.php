<?php

declare(strict_types=1);

// ========== CLIENT MODULE ==========

w('Client/Domain/Entity/Client.php', <<<'PHP'
<?php

namespace App\Client\Domain\Entity;

use App\Client\Infrastructure\Persistence\Doctrine\DoctrineClientRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\UniqueConstraint(name: 'uniq_client_code', fields: ['code'])]
class Client
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

    public function __construct(string $code, string $title, ?string $description = null)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->description = $description;
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
}

PHP);

w('Client/Domain/Entity/ClientComment.php', <<<'PHP'
<?php

namespace App\Client\Domain\Entity;

use App\Client\Infrastructure\Persistence\Doctrine\DoctrineClientCommentRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineClientCommentRepository::class)]
#[ORM\Table(name: 'client_comments')]
class ClientComment
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $clientId, string $content)
    {
        $this->initializeUuid();
        $this->clientId = $clientId;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getClientId(): Uuid { return $this->clientId; }
    public function getContent(): string { return $this->content; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

PHP);

writeException('Client', 'Client', 'Client');

w('Client/Domain/Repository/ClientRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Client\Domain\Repository;

use App\Client\Domain\Entity\Client;
use Symfony\Component\Uid\Uuid;

interface ClientRepositoryInterface
{
    public function save(Client $client): void;

    public function findById(Uuid $id): ?Client;

    /** @return list<Client> */
    public function findAllEnabled(?string $search = null): array;

    /** @return list<Client> */
    public function findAllDisabled(): array;

    public function countEnabled(): int;
}

PHP);

w('Client/Domain/Repository/ClientCommentRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Client\Domain\Repository;

use App\Client\Domain\Entity\ClientComment;
use Symfony\Component\Uid\Uuid;

interface ClientCommentRepositoryInterface
{
    public function save(ClientComment $comment): void;

    /** @return list<ClientComment> */
    public function findByClientId(Uuid $clientId): array;
}

PHP);

w('Client/Infrastructure/Persistence/Doctrine/DoctrineClientRepository.php', <<<'PHP'
<?php

namespace App\Client\Infrastructure\Persistence\Doctrine;

use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Client> */
class DoctrineClientRepository extends ServiceEntityRepository implements ClientRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $client): void
    {
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Client
    {
        return $this->find($id);
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('c.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('c.title LIKE :search OR c.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllDisabled(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isEnabled = :enabled')
            ->setParameter('enabled', false)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countEnabled(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

PHP);

w('Client/Infrastructure/Persistence/Doctrine/DoctrineClientCommentRepository.php', <<<'PHP'
<?php

namespace App\Client\Infrastructure\Persistence\Doctrine;

use App\Client\Domain\Entity\ClientComment;
use App\Client\Domain\Repository\ClientCommentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ClientComment> */
class DoctrineClientCommentRepository extends ServiceEntityRepository implements ClientCommentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientComment::class);
    }

    public function save(ClientComment $comment): void
    {
        $this->getEntityManager()->persist($comment);
        $this->getEntityManager()->flush();
    }

    public function findByClientId(Uuid $clientId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.clientId = :clientId')
            ->setParameter('clientId', $clientId, 'uuid')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Client/Application/Dto/ClientResponseDto.php', <<<'PHP'
<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\Client;

final readonly class ClientResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $description,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Client $client): self
    {
        return new self(
            id: (string) $client->getId(),
            code: $client->getCode(),
            title: $client->getTitle(),
            description: $client->getDescription(),
            isEnabled: $client->isEnabled(),
            createdAt: $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $client->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Client/Application/Dto/ClientCommentResponseDto.php', <<<'PHP'
<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\ClientComment;

final readonly class ClientCommentResponseDto
{
    public function __construct(
        public string $id,
        public string $clientId,
        public string $content,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(ClientComment $comment): self
    {
        return new self(
            id: (string) $comment->getId(),
            clientId: (string) $comment->getClientId(),
            content: $comment->getContent(),
            createdAt: $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->clientId,
            'content' => $this->content,
            'createdAt' => $this->createdAt,
        ];
    }
}

PHP);

w('Client/Application/Dto/ClientDetailResponseDto.php', <<<'PHP'
<?php

namespace App\Client\Application\Dto;

use App\Client\Domain\Entity\Client;

final readonly class ClientDetailResponseDto
{
    /** @param list<array<string, mixed>> $comments */
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $description,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
        public int $projectCount,
        public int $invoiceCount,
        public array $comments,
    ) {
    }

    public static function fromEntity(Client $client, int $projectCount, int $invoiceCount, array $comments): self
    {
        return new self(
            id: (string) $client->getId(),
            code: $client->getCode(),
            title: $client->getTitle(),
            description: $client->getDescription(),
            isEnabled: $client->isEnabled(),
            createdAt: $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $client->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            projectCount: $projectCount,
            invoiceCount: $invoiceCount,
            comments: $comments,
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
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'projectCount' => $this->projectCount,
            'invoiceCount' => $this->invoiceCount,
            'comments' => $this->comments,
        ];
    }
}

PHP);

w('Client/Application/Command/CreateClient/CreateClientCommand.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\CreateClient;

final readonly class CreateClientCommand
{
    public function __construct(
        public string $title,
        public ?string $description = null,
    ) {
    }
}

PHP);

w('Client/Application/Command/CreateClient/CreateClientHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\CreateClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;

final class CreateClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateClientCommand $command): ClientResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::CLIENT);
        $client = new Client($code, $title, $command->description);
        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}

PHP);

w('Client/Application/Command/UpdateClient/UpdateClientCommand.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\UpdateClient;

final readonly class UpdateClientCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
    ) {
    }
}

PHP);

w('Client/Application/Command/UpdateClient/UpdateClientHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\UpdateClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(UpdateClientCommand $command): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($command->id));
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $client->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->description !== null) {
            $client->setDescription($command->description);
        }

        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}

PHP);

w('Client/Application/Command/DeleteClient/DeleteClientCommand.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\DeleteClient;

final readonly class DeleteClientCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Client/Application/Command/DeleteClient/DeleteClientHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\DeleteClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(DeleteClientCommand $command): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($command->id));
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->id);
        }

        $client->disable();
        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}

PHP);

w('Client/Application/Command/RestoreClient/RestoreClientCommand.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\RestoreClient;

final readonly class RestoreClientCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Client/Application/Command/RestoreClient/RestoreClientHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\RestoreClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class RestoreClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(RestoreClientCommand $command): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($command->id));
        if (null === $client) {
            throw ClientNotFoundException::withId($command->id);
        }

        $client->enable();
        $this->clientRepository->save($client);

        return ClientResponseDto::fromEntity($client);
    }
}

PHP);

w('Client/Application/Command/CreateClientComment/CreateClientCommentCommand.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\CreateClientComment;

final readonly class CreateClientCommentCommand
{
    public function __construct(
        public string $clientId,
        public string $content,
    ) {
    }
}

PHP);

w('Client/Application/Command/CreateClientComment/CreateClientCommentHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Command\CreateClientComment;

use App\Client\Application\Dto\ClientCommentResponseDto;
use App\Client\Domain\Entity\ClientComment;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientCommentRepositoryInterface;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateClientCommentHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ClientCommentRepositoryInterface $commentRepository,
    ) {
    }

    public function handle(CreateClientCommentCommand $command): ClientCommentResponseDto
    {
        $clientId = Uuid::fromString($command->clientId);
        $client = $this->clientRepository->findById($clientId);
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($command->clientId);
        }

        $content = FieldValidator::requireNonEmpty($command->content, 'Contenu');
        $comment = new ClientComment($clientId, $content);
        $this->commentRepository->save($comment);

        return ClientCommentResponseDto::fromEntity($comment);
    }
}

PHP);

w('Client/Application/Query/ListClients/ListClientsQuery.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\ListClients;

final readonly class ListClientsQuery
{
    public function __construct(public ?string $search = null) {}
}

PHP);

w('Client/Application/Query/ListClients/ListClientsHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\ListClients;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Repository\ClientRepositoryInterface;

final class ListClientsHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListClientsQuery $query): array
    {
        return array_map(
            static fn ($client) => ClientResponseDto::fromEntity($client)->toArray(),
            $this->clientRepository->findAllEnabled($query->search),
        );
    }
}

PHP);

w('Client/Application/Query/GetClient/GetClientQuery.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\GetClient;

final readonly class GetClientQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Client/Application/Query/GetClient/GetClientHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\GetClient;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetClientHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    public function handle(GetClientQuery $query): ClientResponseDto
    {
        $client = $this->clientRepository->findById(Uuid::fromString($query->id));
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($query->id);
        }

        return ClientResponseDto::fromEntity($client);
    }
}

PHP);

w('Client/Application/Query/GetClientDetail/GetClientDetailQuery.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\GetClientDetail;

final readonly class GetClientDetailQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Client/Application/Query/GetClientDetail/GetClientDetailHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\GetClientDetail;

use App\Client\Application\Dto\ClientCommentResponseDto;
use App\Client\Application\Dto\ClientDetailResponseDto;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\Repository\ClientCommentRepositoryInterface;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetClientDetailHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ClientCommentRepositoryInterface $commentRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(GetClientDetailQuery $query): ClientDetailResponseDto
    {
        $clientId = Uuid::fromString($query->id);
        $client = $this->clientRepository->findById($clientId);
        if (null === $client || !$client->isEnabled()) {
            throw ClientNotFoundException::withId($query->id);
        }

        $comments = array_map(
            static fn ($c) => ClientCommentResponseDto::fromEntity($c)->toArray(),
            $this->commentRepository->findByClientId($clientId),
        );

        return ClientDetailResponseDto::fromEntity(
            $client,
            $this->projectRepository->countByClientId($clientId),
            $this->invoiceRepository->countByClientId($clientId),
            $comments,
        );
    }
}

PHP);

w('Client/Application/Query/ListDeletedClients/ListDeletedClientsHandler.php', <<<'PHP'
<?php

namespace App\Client\Application\Query\ListDeletedClients;

use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Domain\Repository\ClientRepositoryInterface;

final class ListDeletedClientsHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(
            static fn ($client) => ClientResponseDto::fromEntity($client)->toArray(),
            $this->clientRepository->findAllDisabled(),
        );
    }
}

PHP);

w('Client/Presentation/Api/Controller/ClientController.php', <<<'PHP'
<?php

namespace App\Client\Presentation\Api\Controller;

use App\Client\Application\Command\CreateClient\CreateClientCommand;
use App\Client\Application\Command\CreateClient\CreateClientHandler;
use App\Client\Application\Command\CreateClientComment\CreateClientCommentCommand;
use App\Client\Application\Command\CreateClientComment\CreateClientCommentHandler;
use App\Client\Application\Command\DeleteClient\DeleteClientCommand;
use App\Client\Application\Command\DeleteClient\DeleteClientHandler;
use App\Client\Application\Command\UpdateClient\UpdateClientCommand;
use App\Client\Application\Command\UpdateClient\UpdateClientHandler;
use App\Client\Application\Query\GetClient\GetClientHandler;
use App\Client\Application\Query\GetClient\GetClientQuery;
use App\Client\Application\Query\GetClientDetail\GetClientDetailHandler;
use App\Client\Application\Query\GetClientDetail\GetClientDetailQuery;
use App\Client\Application\Query\ListClients\ListClientsHandler;
use App\Client\Application\Query\ListClients\ListClientsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    #[IsGranted('client.clients.view')]
    public function list(Request $request, ListClientsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListClientsQuery($request->query->get('search'))));
    }

    #[Route('', name: 'api_clients_create', methods: ['POST'])]
    #[IsGranted('client.clients.create')]
    public function create(Request $request, CreateClientHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateClientCommand(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_clients_get', methods: ['GET'])]
    #[IsGranted('client.clients.view')]
    public function get(string $id, GetClientHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetClientQuery($id))->toArray());
    }

    #[Route('/{id}/detail', name: 'api_clients_detail', methods: ['GET'])]
    #[IsGranted('client.clients.view')]
    public function detail(string $id, GetClientDetailHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetClientDetailQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_clients_update', methods: ['PUT'])]
    #[IsGranted('client.clients.update')]
    public function update(string $id, Request $request, UpdateClientHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateClientCommand(
            id: $id,
            title: $data['title'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_clients_delete', methods: ['DELETE'])]
    #[IsGranted('client.clients.delete')]
    public function delete(string $id, DeleteClientHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteClientCommand($id))->toArray());
    }

    #[Route('/{id}/comments', name: 'api_clients_comments_create', methods: ['POST'])]
    #[IsGranted('client.comments.create')]
    public function createComment(string $id, Request $request, CreateClientCommentHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateClientCommentCommand(
            clientId: $id,
            content: $data['content'] ?? '',
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part3.php';
