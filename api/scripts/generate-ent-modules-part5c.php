<?php

declare(strict_types=1);

w('Finance/Application/Query/ListInvoices/ListInvoicesHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Query\ListInvoices;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;

final class ListInvoicesHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(
            static fn ($i) => InvoiceResponseDto::fromEntity($i)->toArray(),
            $this->invoiceRepository->findAllEnabled(),
        );
    }
}

PHP);

w('Finance/Application/Query/GetInvoice/GetInvoiceQuery.php', <<<'PHP'
<?php

namespace App\Finance\Application\Query\GetInvoice;

final readonly class GetInvoiceQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Finance/Application/Query/GetInvoice/GetInvoiceHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Query\GetInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(GetInvoiceQuery $query): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($query->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($query->id);
        }

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

PHP);

w('Finance/Presentation/Api/Controller/InvoiceController.php', <<<'PHP'
<?php

namespace App\Finance\Presentation\Api\Controller;

use App\Finance\Application\Command\CreateInvoice\CreateInvoiceCommand;
use App\Finance\Application\Command\CreateInvoice\CreateInvoiceHandler;
use App\Finance\Application\Command\DeleteInvoice\DeleteInvoiceCommand;
use App\Finance\Application\Command\DeleteInvoice\DeleteInvoiceHandler;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceCommand;
use App\Finance\Application\Command\UpdateInvoice\UpdateInvoiceHandler;
use App\Finance\Application\Query\GetInvoice\GetInvoiceHandler;
use App\Finance\Application\Query\GetInvoice\GetInvoiceQuery;
use App\Finance\Application\Query\ListInvoices\ListInvoicesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/invoices')]
final class InvoiceController extends AbstractController
{
    #[Route('', name: 'api_invoices_list', methods: ['GET'])]
    #[IsGranted('finance.invoices.view')]
    public function list(ListInvoicesHandler $handler): JsonResponse
    {
        return $this->json($handler->handle());
    }

    #[Route('', name: 'api_invoices_create', methods: ['POST'])]
    #[IsGranted('finance.invoices.create')]
    public function create(Request $request, CreateInvoiceHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateInvoiceCommand(
            date: $data['date'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            clientId: $data['clientId'] ?? '',
            status: $data['status'] ?? 'draft',
            projectId: $data['projectId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_invoices_get', methods: ['GET'])]
    #[IsGranted('finance.invoices.view')]
    public function get(string $id, GetInvoiceHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetInvoiceQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_invoices_update', methods: ['PUT'])]
    #[IsGranted('finance.invoices.update')]
    public function update(string $id, Request $request, UpdateInvoiceHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateInvoiceCommand(
            id: $id,
            date: $data['date'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            status: $data['status'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_invoices_delete', methods: ['DELETE'])]
    #[IsGranted('finance.invoices.delete')]
    public function delete(string $id, DeleteInvoiceHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteInvoiceCommand($id))->toArray());
    }
}

PHP);

w('Finance/Application/Command/CreateFinancialTransaction/CreateFinancialTransactionCommand.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\CreateFinancialTransaction;

final readonly class CreateFinancialTransactionCommand
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $type,
        public string $category,
        public string $status,
        public string $fromParty,
        public string $toParty,
        public ?string $description = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
    ) {
    }
}

PHP);

w('Finance/Application/Command/CreateFinancialTransaction/CreateFinancialTransactionHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\CreateFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(CreateFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $transaction = new FinancialTransaction(
            date: new \DateTimeImmutable($command->date),
            amount: $command->amount,
            type: TransactionType::from($command->type),
            category: TransactionCategory::from($command->category),
            status: TransactionStatus::from($command->status),
            fromParty: FieldValidator::requireNonEmpty($command->fromParty, 'Émetteur'),
            toParty: FieldValidator::requireNonEmpty($command->toParty, 'Destinataire'),
            description: $command->description,
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
            siteId: $command->siteId ? Uuid::fromString($command->siteId) : null,
        );
        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

PHP);

w('Finance/Application/Command/UpdateFinancialTransaction/UpdateFinancialTransactionCommand.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\UpdateFinancialTransaction;

final readonly class UpdateFinancialTransactionCommand
{
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?float $amount = null,
        public ?string $type = null,
        public ?string $category = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?string $fromParty = null,
        public ?string $toParty = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
    ) {
    }
}

PHP);

w('Finance/Application/Command/UpdateFinancialTransaction/UpdateFinancialTransactionHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\UpdateFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Exception\FinancialTransactionNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(UpdateFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $transaction = $this->transactionRepository->findById(Uuid::fromString($command->id));
        if (null === $transaction || !$transaction->isEnabled()) {
            throw FinancialTransactionNotFoundException::withId($command->id);
        }

        if ($command->date !== null) {
            $transaction->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->amount !== null) {
            $transaction->setAmount($command->amount);
        }
        if ($command->type !== null) {
            $transaction->setType(TransactionType::from($command->type));
        }
        if ($command->category !== null) {
            $transaction->setCategory(TransactionCategory::from($command->category));
        }
        if ($command->description !== null) {
            $transaction->setDescription($command->description);
        }
        if ($command->status !== null) {
            $transaction->setStatus(TransactionStatus::from($command->status));
        }
        if ($command->fromParty !== null) {
            $transaction->setFromParty(FieldValidator::requireNonEmpty($command->fromParty, 'Émetteur'));
        }
        if ($command->toParty !== null) {
            $transaction->setToParty(FieldValidator::requireNonEmpty($command->toParty, 'Destinataire'));
        }
        if ($command->clientId !== null) {
            $transaction->setClientId($command->clientId !== '' ? Uuid::fromString($command->clientId) : null);
        }
        if ($command->projectId !== null) {
            $transaction->setProjectId($command->projectId !== '' ? Uuid::fromString($command->projectId) : null);
        }
        if ($command->siteId !== null) {
            $transaction->setSiteId($command->siteId !== '' ? Uuid::fromString($command->siteId) : null);
        }

        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

PHP);

w('Finance/Application/Command/DeleteFinancialTransaction/DeleteFinancialTransactionCommand.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\DeleteFinancialTransaction;

final readonly class DeleteFinancialTransactionCommand
{
    public function __construct(public string $id) {}
}

PHP);

w('Finance/Application/Command/DeleteFinancialTransaction/DeleteFinancialTransactionHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Command\DeleteFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Exception\FinancialTransactionNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(DeleteFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $transaction = $this->transactionRepository->findById(Uuid::fromString($command->id));
        if (null === $transaction || !$transaction->isEnabled()) {
            throw FinancialTransactionNotFoundException::withId($command->id);
        }

        $transaction->disable();
        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

PHP);

w('Finance/Application/Query/ListFinancialTransactions/ListFinancialTransactionsHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Query\ListFinancialTransactions;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;

final class ListFinancialTransactionsHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(
            static fn ($t) => FinancialTransactionResponseDto::fromEntity($t)->toArray(),
            $this->transactionRepository->findAllEnabled(),
        );
    }
}

PHP);

w('Finance/Application/Query/GetFinancialTransaction/GetFinancialTransactionQuery.php', <<<'PHP'
<?php

namespace App\Finance\Application\Query\GetFinancialTransaction;

final readonly class GetFinancialTransactionQuery
{
    public function __construct(public string $id) {}
}

PHP);

w('Finance/Application/Query/GetFinancialTransaction/GetFinancialTransactionHandler.php', <<<'PHP'
<?php

namespace App\Finance\Application\Query\GetFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Exception\FinancialTransactionNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(GetFinancialTransactionQuery $query): FinancialTransactionResponseDto
    {
        $transaction = $this->transactionRepository->findById(Uuid::fromString($query->id));
        if (null === $transaction || !$transaction->isEnabled()) {
            throw FinancialTransactionNotFoundException::withId($query->id);
        }

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

PHP);

w('Finance/Presentation/Api/Controller/FinancialTransactionController.php', <<<'PHP'
<?php

namespace App\Finance\Presentation\Api\Controller;

use App\Finance\Application\Command\CreateFinancialTransaction\CreateFinancialTransactionCommand;
use App\Finance\Application\Command\CreateFinancialTransaction\CreateFinancialTransactionHandler;
use App\Finance\Application\Command\DeleteFinancialTransaction\DeleteFinancialTransactionCommand;
use App\Finance\Application\Command\DeleteFinancialTransaction\DeleteFinancialTransactionHandler;
use App\Finance\Application\Command\UpdateFinancialTransaction\UpdateFinancialTransactionCommand;
use App\Finance\Application\Command\UpdateFinancialTransaction\UpdateFinancialTransactionHandler;
use App\Finance\Application\Query\GetFinancialTransaction\GetFinancialTransactionHandler;
use App\Finance\Application\Query\GetFinancialTransaction\GetFinancialTransactionQuery;
use App\Finance\Application\Query\ListFinancialTransactions\ListFinancialTransactionsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/financial-transactions')]
final class FinancialTransactionController extends AbstractController
{
    #[Route('', name: 'api_financial_transactions_list', methods: ['GET'])]
    #[IsGranted('finance.transactions.view')]
    public function list(ListFinancialTransactionsHandler $handler): JsonResponse
    {
        return $this->json($handler->handle());
    }

    #[Route('', name: 'api_financial_transactions_create', methods: ['POST'])]
    #[IsGranted('finance.transactions.create')]
    public function create(Request $request, CreateFinancialTransactionHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new CreateFinancialTransactionCommand(
            date: $data['date'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            type: $data['type'] ?? 'expense',
            category: $data['category'] ?? 'OtherExpense',
            status: $data['status'] ?? 'pending',
            fromParty: $data['fromParty'] ?? '',
            toParty: $data['toParty'] ?? '',
            description: $data['description'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
            siteId: $data['siteId'] ?? null,
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_financial_transactions_get', methods: ['GET'])]
    #[IsGranted('finance.transactions.view')]
    public function get(string $id, GetFinancialTransactionHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetFinancialTransactionQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_financial_transactions_update', methods: ['PUT'])]
    #[IsGranted('finance.transactions.update')]
    public function update(string $id, Request $request, UpdateFinancialTransactionHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $result = $handler->handle(new UpdateFinancialTransactionCommand(
            id: $id,
            date: $data['date'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            type: $data['type'] ?? null,
            category: $data['category'] ?? null,
            description: $data['description'] ?? null,
            status: $data['status'] ?? null,
            fromParty: $data['fromParty'] ?? null,
            toParty: $data['toParty'] ?? null,
            clientId: $data['clientId'] ?? null,
            projectId: $data['projectId'] ?? null,
            siteId: $data['siteId'] ?? null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}', name: 'api_financial_transactions_delete', methods: ['DELETE'])]
    #[IsGranted('finance.transactions.delete')]
    public function delete(string $id, DeleteFinancialTransactionHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new DeleteFinancialTransactionCommand($id))->toArray());
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part6.php';
