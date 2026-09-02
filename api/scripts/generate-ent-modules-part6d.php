<?php

declare(strict_types=1);

w('Stock/Application/Command/UpdateStockMovement/UpdateStockMovementCommand.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\UpdateStockMovement;

final readonly class UpdateStockMovementCommand
{
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?float $quantity = null,
        public ?string $unit = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
    ) {
    }
}

PHP);

w('Stock/Application/Command/UpdateStockMovement/UpdateStockMovementHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\UpdateStockMovement;

use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Exception\StockMovementNotFoundException;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(UpdateStockMovementCommand $command): StockMovementResponseDto
    {
        $movement = $this->movementRepository->findById(Uuid::fromString($command->id));
        if (null === $movement || !$movement->isEnabled()) {
            throw StockMovementNotFoundException::withId($command->id);
        }

        if ($command->date !== null) {
            $movement->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->quantity !== null) {
            $movement->setQuantity($command->quantity);
        }
        if ($command->unit !== null) {
            $movement->setUnit(FieldValidator::requireNonEmpty($command->unit, 'Unité'));
        }
        if ($command->clientId !== null) {
            $movement->setClientId($command->clientId !== '' ? Uuid::fromString($command->clientId) : null);
        }
        if ($command->projectId !== null) {
            $movement->setProjectId($command->projectId !== '' ? Uuid::fromString($command->projectId) : null);
        }
        if ($command->siteId !== null) {
            $movement->setSiteId($command->siteId !== '' ? Uuid::fromString($command->siteId) : null);
        }

        $this->movementRepository->save($movement);

        $lines = array_map(
            static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
            $this->lineRepository->findByMovementId($movement->getId()),
        );

        return new StockMovementResponseDto(
            id: (string) $movement->getId(),
            date: $movement->getDate()->format('Y-m-d'),
            quantity: $movement->getQuantity(),
            unit: $movement->getUnit(),
            clientId: $movement->getClientId()?->toRfc4122(),
            projectId: $movement->getProjectId()?->toRfc4122(),
            siteId: $movement->getSiteId()?->toRfc4122(),
            lines: $lines,
            isEnabled: $movement->isEnabled(),
            createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}

PHP);

w('Stock/Application/Command/DeleteStockMovement/DeleteStockMovementCommand.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\DeleteStockMovement;

final readonly class DeleteStockMovementCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Stock/Application/Command/DeleteStockMovement/DeleteStockMovementHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\DeleteStockMovement;

use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Exception\StockMovementNotFoundException;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(DeleteStockMovementCommand $command): StockMovementResponseDto
    {
        $movement = $this->movementRepository->findById(Uuid::fromString($command->id));
        if (null === $movement || !$movement->isEnabled()) {
            throw StockMovementNotFoundException::withId($command->id);
        }

        $movement->disable();
        $this->movementRepository->save($movement);

        $lines = array_map(
            static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
            $this->lineRepository->findByMovementId($movement->getId()),
        );

        return new StockMovementResponseDto(
            id: (string) $movement->getId(),
            date: $movement->getDate()->format('Y-m-d'),
            quantity: $movement->getQuantity(),
            unit: $movement->getUnit(),
            clientId: $movement->getClientId()?->toRfc4122(),
            projectId: $movement->getProjectId()?->toRfc4122(),
            siteId: $movement->getSiteId()?->toRfc4122(),
            lines: $lines,
            isEnabled: $movement->isEnabled(),
            createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}

PHP);

w('Stock/Application/Query/ListStockMovements/ListStockMovementsHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Query\ListStockMovements;

use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;

final class ListStockMovementsHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        $result = [];
        foreach ($this->movementRepository->findAllEnabled() as $movement) {
            $lines = array_map(
                static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
                $this->lineRepository->findByMovementId($movement->getId()),
            );
            $result[] = (new StockMovementResponseDto(
                id: (string) $movement->getId(),
                date: $movement->getDate()->format('Y-m-d'),
                quantity: $movement->getQuantity(),
                unit: $movement->getUnit(),
                clientId: $movement->getClientId()?->toRfc4122(),
                projectId: $movement->getProjectId()?->toRfc4122(),
                siteId: $movement->getSiteId()?->toRfc4122(),
                lines: $lines,
                isEnabled: $movement->isEnabled(),
                createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
                updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ))->toArray();
        }

        return $result;
    }
}

PHP);

w('Stock/Application/Query/GetStockMovement/GetStockMovementQuery.php', <<<'PHP'
<?php

namespace App\Stock\Application\Query\GetStockMovement;

final readonly class GetStockMovementQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Stock/Application/Query/GetStockMovement/GetStockMovementHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Query\GetStockMovement;

use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Exception\StockMovementNotFoundException;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(GetStockMovementQuery $query): StockMovementResponseDto
    {
        $movement = $this->movementRepository->findById(Uuid::fromString($query->id));
        if (null === $movement || !$movement->isEnabled()) {
            throw StockMovementNotFoundException::withId($query->id);
        }

        $lines = array_map(
            static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
            $this->lineRepository->findByMovementId($movement->getId()),
        );

        return new StockMovementResponseDto(
            id: (string) $movement->getId(),
            date: $movement->getDate()->format('Y-m-d'),
            quantity: $movement->getQuantity(),
            unit: $movement->getUnit(),
            clientId: $movement->getClientId()?->toRfc4122(),
            projectId: $movement->getProjectId()?->toRfc4122(),
            siteId: $movement->getSiteId()?->toRfc4122(),
            lines: $lines,
            isEnabled: $movement->isEnabled(),
            createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}

PHP);

w('Stock/Presentation/Api/Controller/StockMovementController.php', <<<'PHP'
<?php

namespace App\Stock\Presentation\Api\Controller;

use App\Stock\Application\Command\CreateStockMovement\CreateStockMovementCommand;
use App\Stock\Application\Command\CreateStockMovement\CreateStockMovementHandler;
use App\Stock\Application\Command\DeleteStockMovement\DeleteStockMovementCommand;
use App\Stock\Application\Command\DeleteStockMovement\DeleteStockMovementHandler;
use App\Stock\Application\Command\UpdateStockMovement\UpdateStockMovementCommand;
use App\Stock\Application\Command\UpdateStockMovement\UpdateStockMovementHandler;
use App\Stock\Application\Query\GetStockMovement\GetStockMovementHandler;
use App\Stock\Application\Query\GetStockMovement\GetStockMovementQuery;
use App\Stock\Application\Query\ListStockMovements\ListStockMovementsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stock-movements')]
final class StockMovementController extends AbstractController
{
    #[Route('', name: 'api_stock_movements_list', methods: ['GET'])]
    #[IsGranted('stock.movements.view')]
    public function list(ListStockMovementsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle());
    }

    #[Route('', name: 'api_stock_movements_create', methods: ['POST'])]
    #[IsGranted('stock.movements.create')]
    public function create(Request $request, CreateStockMovementHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateStockMovementCommand(
            date: $data['date'] ?? '',
            quantity: (float) ($data['quantity'] ?? 0),
            unit: $data['unit'] ?? '',
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
            siteId: $data['siteId'] ?? null,
            lines: $data['lines'] ?? [],
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_stock_movements_get', methods: ['GET'])]
    #[IsGranted('stock.movements.view')]
    public function get(string $id, GetStockMovementHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetStockMovementQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_stock_movements_update', methods: ['PUT'])]
    #[IsGranted('stock.movements.update')]
    public function update(string $id, Request $request, UpdateStockMovementHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateStockMovementCommand(
            id: $id,
            date: $data['date'] ?? null,
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            unit: $data['unit'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
            siteId: $data['siteId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_stock_movements_delete', methods: ['DELETE'])]
    #[IsGranted('stock.movements.delete')]
    public function delete(string $id, DeleteStockMovementHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteStockMovementCommand($id))->toArray());
    }
}

PHP);

// ========== DOCUMENT MODULE ==========

w('Document/Domain/Enum/DocumentOwnerType.php', <<<'PHP'
<?php

namespace App\Document\Domain\Enum;

enum DocumentOwnerType: string
{
    case CLIENT = 'client';
    case PROJECT = 'project';
    case SITE = 'site';
}

PHP);

w('Document/Domain/Entity/Document.php', <<<'PHP'
<?php

namespace App\Document\Domain\Entity;

use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Infrastructure\Persistence\Doctrine\DoctrineDocumentRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineDocumentRepository::class)]
#[ORM\Table(name: 'documents')]
class Document
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 255)]
    private string $fileName;

    #[ORM\Column(length: 500)]
    private string $filePath;

    #[ORM\Column(enumType: DocumentOwnerType::class)]
    private DocumentOwnerType $ownerType;

    #[ORM\Column(type: 'uuid')]
    private Uuid $ownerId;

    public function __construct(
        string $title,
        string $fileName,
        string $filePath,
        DocumentOwnerType $ownerType,
        Uuid $ownerId,
        ?string $description = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->title = $title;
        $this->fileName = $fileName;
        $this->filePath = $filePath;
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->description = $description;
    }

    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getFileName(): string { return $this->fileName; }
    public function getFilePath(): string { return $this->filePath; }
    public function getOwnerType(): DocumentOwnerType { return $this->ownerType; }
    public function getOwnerId(): Uuid { return $this->ownerId; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
}

PHP);

writeException('Document', 'Document', 'Document');

w('Document/Domain/Repository/DocumentRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Document\Domain\Repository;

use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use Symfony\Component\Uid\Uuid;

interface DocumentRepositoryInterface
{
    public function save(Document $document): void;

    public function findById(Uuid $id): ?Document;

    /** @return list<Document> */
    public function findByOwner(DocumentOwnerType $ownerType, Uuid $ownerId): array;
}

PHP);

w('Document/Infrastructure/Persistence/Doctrine/DoctrineDocumentRepository.php', <<<'PHP'
<?php

namespace App\Document\Infrastructure\Persistence\Doctrine;

use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Document> */
class DoctrineDocumentRepository extends ServiceEntityRepository implements DocumentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function save(Document $document): void
    {
        $this->getEntityManager()->persist($document);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Document
    {
        return $this->find($id);
    }

    public function findByOwner(DocumentOwnerType $ownerType, Uuid $ownerId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.ownerType = :ownerType')
            ->andWhere('d.ownerId = :ownerId')
            ->andWhere('d.isEnabled = :enabled')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerId', $ownerId, 'uuid')
            ->setParameter('enabled', true)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Document/Application/Service/DocumentUploadService.php', <<<'PHP'
<?php

namespace App\Document\Application\Service;

use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class DocumentUploadService
{
    public const PUBLIC_PATH_PREFIX = '/uploads/documents/';
    private const RELATIVE_DIR = 'public/uploads/documents';

    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly string $projectDir,
    ) {
    }

    public function upload(
        UploadedFile $file,
        string $title,
        DocumentOwnerType $ownerType,
        Uuid $ownerId,
        ?string $description = null,
    ): Document {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide ou upload incomplet.');
        }

        $title = FieldValidator::requireNonEmpty($title, 'Titre');
        $directory = $this->projectDir.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, self::RELATIVE_DIR);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage des documents.');
        }

        $originalName = $file->getClientOriginalName();
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName) ?? 'document';
        $filename = Uuid::v7()->toRfc4122().'_'.$safeName;
        $file->move($directory, $filename);

        $document = new Document(
            title: $title,
            fileName: $originalName,
            filePath: self::PUBLIC_PATH_PREFIX.$filename,
            ownerType: $ownerType,
            ownerId: $ownerId,
            description: $description,
        );
        $this->documentRepository->save($document);

        return $document;
    }

    public function deleteFile(string $filePath): void
    {
        if (!str_starts_with($filePath, self::PUBLIC_PATH_PREFIX)) {
            return;
        }

        $relative = ltrim(str_replace(self::PUBLIC_PATH_PREFIX, 'uploads/documents/', $filePath), '/');
        $absolute = $this->projectDir.\DIRECTORY_SEPARATOR.'public'.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $relative);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}

PHP);

w('Document/Application/Dto/DocumentResponseDto.php', <<<'PHP'
<?php

namespace App\Document\Application\Dto;

use App\Document\Domain\Entity\Document;

final readonly class DocumentResponseDto
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $fileName,
        public string $filePath,
        public string $ownerType,
        public string $ownerId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Document $document): self
    {
        return new self(
            id: (string) $document->getId(),
            title: $document->getTitle(),
            description: $document->getDescription(),
            fileName: $document->getFileName(),
            filePath: $document->getFilePath(),
            ownerType: $document->getOwnerType()->value,
            ownerId: (string) $document->getOwnerId(),
            isEnabled: $document->isEnabled(),
            createdAt: $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $document->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'fileName' => $this->fileName,
            'filePath' => $this->filePath,
            'ownerType' => $this->ownerType,
            'ownerId' => $this->ownerId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Document/Application/Command/DeleteDocument/DeleteDocumentCommand.php', <<<'PHP'
<?php

namespace App\Document\Application\Command\DeleteDocument;

final readonly class DeleteDocumentCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Document/Application/Command/DeleteDocument/DeleteDocumentHandler.php', <<<'PHP'
<?php

namespace App\Document\Application\Command\DeleteDocument;

use App\Document\Application\Dto\DocumentResponseDto;
use App\Document\Application\Service\DocumentUploadService;
use App\Document\Domain\Exception\DocumentNotFoundException;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentUploadService $uploadService,
    ) {
    }

    public function handle(DeleteDocumentCommand $command): DocumentResponseDto
    {
        $document = $this->documentRepository->findById(Uuid::fromString($command->id));
        if (null === $document || !$document->isEnabled()) {
            throw DocumentNotFoundException::withId($command->id);
        }

        $this->uploadService->deleteFile($document->getFilePath());
        $document->disable();
        $this->documentRepository->save($document);

        return DocumentResponseDto::fromEntity($document);
    }
}

PHP);

w('Document/Application/Query/ListDocumentsByOwner/ListDocumentsByOwnerQuery.php', <<<'PHP'
<?php

namespace App\Document\Application\Query\ListDocumentsByOwner;

final readonly class ListDocumentsByOwnerQuery
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
    ) {
    }
}

PHP);

w('Document/Application/Query/ListDocumentsByOwner/ListDocumentsByOwnerHandler.php', <<<'PHP'
<?php

namespace App\Document\Application\Query\ListDocumentsByOwner;

use App\Document\Application\Dto\DocumentResponseDto;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListDocumentsByOwnerHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListDocumentsByOwnerQuery $query): array
    {
        $documents = $this->documentRepository->findByOwner(
            DocumentOwnerType::from($query->ownerType),
            Uuid::fromString($query->ownerId),
        );

        return array_map(
            static fn ($d) => DocumentResponseDto::fromEntity($d)->toArray(),
            $documents,
        );
    }
}

PHP);

w('Document/Presentation/Api/Controller/DocumentController.php', <<<'PHP'
<?php

namespace App\Document\Presentation\Api\Controller;

use App\Document\Application\Command\DeleteDocument\DeleteDocumentCommand;
use App\Document\Application\Command\DeleteDocument\DeleteDocumentHandler;
use App\Document\Application\Query\ListDocumentsByOwner\ListDocumentsByOwnerHandler;
use App\Document\Application\Query\ListDocumentsByOwner\ListDocumentsByOwnerQuery;
use App\Document\Application\Service\DocumentUploadService;
use App\Document\Application\Dto\DocumentResponseDto;
use App\Document\Domain\Enum\DocumentOwnerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/documents')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'api_documents_list', methods: ['GET'])]
    #[IsGranted('document.documents.view')]
    public function list(Request $request, ListDocumentsByOwnerHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListDocumentsByOwnerQuery(
            ownerType: (string) $request->query->get('ownerType', ''),
            ownerId: (string) $request->query->get('ownerId', ''),
        )));
    }

    #[Route('/upload', name: 'api_documents_upload', methods: ['POST'])]
    #[IsGranted('document.documents.upload')]
    public function upload(Request $request, DocumentUploadService $uploadService): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['error' => 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $document = $uploadService->upload(
            file: $file,
            title: (string) $request->request->get('title', $file->getClientOriginalName()),
            ownerType: DocumentOwnerType::from((string) $request->request->get('ownerType', 'client')),
            ownerId: Uuid::fromString((string) $request->request->get('ownerId', '')),
            description: $request->request->get('description'),
        );

        return $this->json(DocumentResponseDto::fromEntity($document)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_documents_delete', methods: ['DELETE'])]
    #[IsGranted('document.documents.delete')]
    public function delete(string $id, DeleteDocumentHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteDocumentCommand($id))->toArray());
    }
}

PHP);

// Update services.yaml for StockMovementLineRepository
$servicesPath = dirname(__DIR__) . '/config/services.yaml';
$servicesContent = file_get_contents($servicesPath);
if ($servicesContent !== false && !str_contains($servicesContent, 'StockMovementLineRepositoryInterface')) {
    $alias = <<<'YAML'

    App\Stock\Domain\Repository\StockMovementLineRepositoryInterface:
        alias: App\Stock\Infrastructure\Persistence\Doctrine\DoctrineStockMovementLineRepository
YAML;
    file_put_contents($servicesPath, rtrim($servicesContent).$alias."\n");
    echo "Updated: config/services.yaml (StockMovementLineRepository alias)\n";
}
