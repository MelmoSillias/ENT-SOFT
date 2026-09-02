<?php

declare(strict_types=1);

w('Finance/Infrastructure/Persistence/Doctrine/DoctrineInvoiceRepository.php', <<<'PHP'
<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Invoice> */
class DoctrineInvoiceRepository extends ServiceEntityRepository implements InvoiceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $invoice): void
    {
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Invoice
    {
        return $this->find($id);
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('i.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByClientId(Uuid $clientId): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.clientId = :clientId')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('clientId', $clientId, 'uuid')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(InvoiceStatus $status): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->andWhere('i.isEnabled = :enabled')
            ->setParameter('status', $status)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

PHP);

w('Finance/Infrastructure/Persistence/Doctrine/DoctrineFinancialTransactionRepository.php', <<<'PHP'
<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<FinancialTransaction> */
class DoctrineFinancialTransactionRepository extends ServiceEntityRepository implements FinancialTransactionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialTransaction::class);
    }

    public function save(FinancialTransaction $transaction): void
    {
        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?FinancialTransaction
    {
        return $this->find($id);
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

PHP);

w('Finance/Application/Dto/InvoiceResponseDto.php', <<<'PHP'
<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\Invoice;

final readonly class InvoiceResponseDto
{
    public function __construct(
        public string $id,
        public string $number,
        public string $date,
        public float $amount,
        public string $status,
        public string $clientId,
        public ?string $projectId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Invoice $invoice): self
    {
        return new self(
            id: (string) $invoice->getId(),
            number: $invoice->getNumber(),
            date: $invoice->getDate()->format('Y-m-d'),
            amount: $invoice->getAmount(),
            status: $invoice->getStatus()->value,
            clientId: (string) $invoice->getClientId(),
            projectId: $invoice->getProjectId()?->toRfc4122(),
            isEnabled: $invoice->isEnabled(),
            createdAt: $invoice->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $invoice->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date,
            'amount' => $this->amount,
            'status' => $this->status,
            'clientId' => $this->clientId,
            'projectId' => $this->projectId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Finance/Application/Dto/FinancialTransactionResponseDto.php', <<<'PHP'
<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\FinancialTransaction;

final readonly class FinancialTransactionResponseDto
{
    public function __construct(
        public string $id,
        public string $date,
        public float $amount,
        public string $type,
        public string $category,
        public ?string $description,
        public string $status,
        public string $fromParty,
        public string $toParty,
        public ?string $clientId,
        public ?string $projectId,
        public ?string $siteId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(FinancialTransaction $t): self
    {
        return new self(
            id: (string) $t->getId(),
            date: $t->getDate()->format('Y-m-d'),
            amount: $t->getAmount(),
            type: $t->getType()->value,
            category: $t->getCategory()->value,
            description: $t->getDescription(),
            status: $t->getStatus()->value,
            fromParty: $t->getFromParty(),
            toParty: $t->getToParty(),
            clientId: $t->getClientId()?->toRfc4122(),
            projectId: $t->getProjectId()?->toRfc4122(),
            siteId: $t->getSiteId()?->toRfc4122(),
            isEnabled: $t->isEnabled(),
            createdAt: $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $t->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'amount' => $this->amount,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'fromParty' => $this->fromParty,
            'toParty' => $this->toParty,
            'clientId' => $this->clientId,
            'projectId' => $this->projectId,
            'siteId' => $this->siteId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

PHP);

w('Finance/Application/Command/CreateInvoice/CreateInvoiceCommand.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\CreateInvoice;

final readonly class CreateInvoiceCommand
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $clientId,
        public string $status = 'draft',
        public ?string $projectId = null,
    ) {
    }
}

PHP);

w('Finance/Application/Command/CreateInvoice/CreateInvoiceHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\CreateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use Symfony\Component\Uid\Uuid;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateInvoiceCommand $command): InvoiceResponseDto
    {
        $number = $this->codeGenerator->generate(ReferenceSequenceType::INVOICE);
        $invoice = new Invoice(
            number: $number,
            date: new \DateTimeImmutable($command->date),
            amount: $command->amount,
            clientId: Uuid::fromString($command->clientId),
            status: InvoiceStatus::from($command->status),
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
        );
        $this->invoiceRepository->save($invoice);

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

PHP);

w('Finance/Application/Command/UpdateInvoice/UpdateInvoiceCommand.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\UpdateInvoice;

final readonly class UpdateInvoiceCommand
{
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?float $amount = null,
        public ?string $status = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
    ) {
    }
}

PHP);

w('Finance/Application/Command/UpdateInvoice/UpdateInvoiceHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\UpdateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(UpdateInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        if ($command->date !== null) {
            $invoice->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->amount !== null) {
            $invoice->setAmount($command->amount);
        }
        if ($command->status !== null) {
            $invoice->setStatus(InvoiceStatus::from($command->status));
        }
        if ($command->clientId !== null) {
            $invoice->setClientId(Uuid::fromString($command->clientId));
        }
        if ($command->projectId !== null) {
            $invoice->setProjectId($command->projectId !== '' ? Uuid::fromString($command->projectId) : null);
        }

        $this->invoiceRepository->save($invoice);

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

PHP);

w('Finance/Application/Command/DeleteInvoice/DeleteInvoiceCommand.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\DeleteInvoice;

final readonly class DeleteInvoiceCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Finance/Application/Command/DeleteInvoice/DeleteInvoiceHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\DeleteInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(DeleteInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        $invoice->disable();
        $this->invoiceRepository->save($invoice);

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part5c.php';
