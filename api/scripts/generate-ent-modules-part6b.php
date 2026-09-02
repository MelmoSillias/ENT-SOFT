<?php

declare(strict_types=1);

w('Stock/Infrastructure/Persistence/Doctrine/DoctrineEquipmentRepository.php', <<<'PHP'
<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\Stock\Domain\Entity\Equipment;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Equipment> */
class DoctrineEquipmentRepository extends ServiceEntityRepository implements EquipmentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipment::class);
    }

    public function save(Equipment $equipment): void
    {
        $this->getEntityManager()->persist($equipment);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Equipment
    {
        return $this->find($id);
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('e.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('e.title LIKE :search OR e.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }
}

PHP);

w('Stock/Infrastructure/Persistence/Doctrine/DoctrineStockMovementRepository.php', <<<'PHP'
<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\Stock\Domain\Entity\StockMovement;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<StockMovement> */
class DoctrineStockMovementRepository extends ServiceEntityRepository implements StockMovementRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    public function save(StockMovement $movement): void
    {
        $this->getEntityManager()->persist($movement);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?StockMovement
    {
        return $this->find($id);
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('m.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Stock/Infrastructure/Persistence/Doctrine/DoctrineStockMovementLineRepository.php', <<<'PHP'
<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\Stock\Domain\Entity\StockMovementLine;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<StockMovementLine> */
class DoctrineStockMovementLineRepository extends ServiceEntityRepository implements StockMovementLineRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovementLine::class);
    }

    public function save(StockMovementLine $line): void
    {
        $this->getEntityManager()->persist($line);
        $this->getEntityManager()->flush();
    }

    public function findByMovementId(Uuid $movementId): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.movement', 'm')
            ->andWhere('m.id = :movementId')
            ->setParameter('movementId', $movementId, 'uuid')
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Stock/Application/Dto/EquipmentResponseDto.php', <<<'PHP'
<?php

namespace App\Stock\Application\Dto;

use App\Stock\Domain\Entity\Equipment;

final readonly class EquipmentResponseDto
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

    public static function fromEntity(Equipment $equipment): self
    {
        return new self(
            id: (string) $equipment->getId(),
            code: $equipment->getCode(),
            title: $equipment->getTitle(),
            description: $equipment->getDescription(),
            clientId: $equipment->getClientId()?->toRfc4122(),
            isEnabled: $equipment->isEnabled(),
            createdAt: $equipment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $equipment->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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

w('Stock/Application/Dto/StockMovementLineResponseDto.php', <<<'PHP'
<?php

namespace App\Stock\Application\Dto;

use App\Stock\Domain\Entity\StockMovementLine;

final readonly class StockMovementLineResponseDto
{
    public function __construct(
        public string $id,
        public string $equipmentId,
        public float $quantity,
    ) {
    }

    public static function fromEntity(StockMovementLine $line): self
    {
        return new self(
            id: (string) $line->getId(),
            equipmentId: (string) $line->getEquipmentId(),
            quantity: $line->getQuantity(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'equipmentId' => $this->equipmentId,
            'quantity' => $this->quantity,
        ];
    }
}

PHP);

w('Stock/Application/Dto/StockMovementResponseDto.php', <<<'PHP'
<?php

namespace App\Stock\Application\Dto;

use App\Stock\Domain\Entity\StockMovement;

final readonly class StockMovementResponseDto
{
    /** @param list<array<string, mixed>> $lines */
    public function __construct(
        public string $id,
        public string $date,
        public float $quantity,
        public string $unit,
        public ?string $clientId,
        public ?string $projectId,
        public ?string $siteId,
        public array $lines,
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
            'date' => $this->date,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'clientId' => $this->clientId,
            'projectId' => $this->projectId,
            'siteId' => $this->siteId,
            'lines' => $this->lines,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Stock/Application/Command/CreateEquipment/CreateEquipmentCommand.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\CreateEquipment;

final readonly class CreateEquipmentCommand
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $clientId = null,
    ) {
    }
}

PHP);

w('Stock/Application/Command/CreateEquipment/CreateEquipmentHandler.php', <<<'PHP'
<?php

namespace App\Stock\Application\Command\CreateEquipment;

use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Entity\Equipment;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateEquipmentCommand $command): EquipmentResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::EQUIPMENT);
        $equipment = new Equipment(
            code: $code,
            title: $title,
            description: $command->description,
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
        );
        $this->equipmentRepository->save($equipment);

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part6c.php';
