<?php

declare(strict_types=1);

/**
 * Generates all ENT-SOFT backend module PHP files.
 * Run from api/: php scripts/generate-ent-modules.php
 */

$base = dirname(__DIR__) . '/src';
$created = [];

function writeFile(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: $dir");
    }
    file_put_contents($path, $content);
}

function w(string $rel, string $content): void
{
    global $base, $created;
    $path = $base . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    writeFile($path, $content);
    $created[] = $rel;
    echo "Created: $rel\n";
}

function ns(string $module): string
{
    return "App\\$module";
}

function exceptionContent(string $module, string $entity, string $label): string
{
    return <<<PHP
<?php

namespace App\\{$module}\\Domain\\Exception;

use App\\SharedKernel\\Domain\\Exception\\DomainException;

final class {$entity}NotFoundException extends DomainException
{
    public static function withId(string \$id): self
    {
        return new self(sprintf('{$label} introuvable : %s.', \$id));
    }
}

PHP;
}

function writeException(string $module, string $entity, string $label): void
{
    w("{$module}/Domain/Exception/{$entity}NotFoundException.php", exceptionContent($module, $entity, $label));
}

function repoInterfaceContent(string $module, string $entity, string $body): string
{
    return <<<PHP
<?php

namespace App\\{$module}\\Domain\\Repository;

{$body}

PHP;
}

function doctrineRepoHeader(string $module, string $entity): string
{
    return <<<PHP
<?php

namespace App\\{$module}\\Infrastructure\\Persistence\\Doctrine;

use App\\{$module}\\Domain\\Entity\\{$entity};
use App\\{$module}\\Domain\\Repository\\{$entity}RepositoryInterface;
use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;
use Doctrine\\Persistence\\ManagerRegistry;
use Symfony\\Component\\Uid\\Uuid;

/** @extends ServiceEntityRepository<{$entity}> */
class Doctrine{$entity}Repository extends ServiceEntityRepository implements {$entity}RepositoryInterface
{
    public function __construct(ManagerRegistry \$registry)
    {
        parent::__construct(\$registry, {$entity}::class);
    }

PHP;
}

function dtoHeader(string $module, string $entity): string
{
    return "namespace App\\{$module}\\Application\\Dto;\n\nuse App\\{$module}\\Domain\\Entity\\{$entity};\n";
}

function commandHeader(string $module, string $action, string $entity): string
{
    return "namespace App\\{$module}\\Application\\Command\\{$action}{$entity};\n\n";
}

function queryHeader(string $module, string $action, string $entity): string
{
    return "namespace App\\{$module}\\Application\\Query\\{$action}{$entity};\n\n";
}

function handlerHeader(string $module, string $layer, string $action, string $entity): string
{
    return "namespace App\\{$module}\\Application\\{$layer}\\{$action}{$entity};\n\n";
}

function controllerHeader(string $module, string $entity): string
{
    return <<<PHP
<?php

namespace App\\{$module}\\Presentation\\Api\\Controller;

use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;
use Symfony\\Component\\HttpFoundation\\JsonResponse;
use Symfony\\Component\\HttpFoundation\\Request;
use Symfony\\Component\\HttpFoundation\\Response;
use Symfony\\Component\\Routing\\Attribute\\Route;
use Symfony\\Component\\Security\\Http\\Attribute\\IsGranted;

PHP;
}

// ========== UPDATED SHARED FILES ==========

w('AccessAudit/Domain/PermissionCatalog.php', <<<'PHP'
<?php

namespace App\AccessAudit\Domain;

use App\IdentityAccess\Domain\Enum\Role;

/**
 * Catalogue fixe des permissions ENT-SOFT.
 *
 * @phpstan-type PermissionDef array{code: string, libelle: string, module: string, description: string|null}
 */
final class PermissionCatalog
{
    /** @return list<PermissionDef> */
    public static function all(): array
    {
        return [
            ['code' => 'dashboard.view', 'libelle' => 'Voir le tableau de bord', 'module' => 'dashboard', 'description' => 'Accéder au tableau de bord'],
            ['code' => 'client.clients.view', 'libelle' => 'Consulter les clients', 'module' => 'client', 'description' => 'Lister et consulter les clients'],
            ['code' => 'client.clients.create', 'libelle' => 'Créer un client', 'module' => 'client', 'description' => 'Créer un client'],
            ['code' => 'client.clients.update', 'libelle' => 'Modifier un client', 'module' => 'client', 'description' => 'Modifier un client existant'],
            ['code' => 'client.clients.delete', 'libelle' => 'Supprimer un client', 'module' => 'client', 'description' => 'Mettre un client à la corbeille'],
            ['code' => 'client.comments.create', 'libelle' => 'Commenter un client', 'module' => 'client', 'description' => 'Ajouter un commentaire sur un client'],
            ['code' => 'site.sites.view', 'libelle' => 'Consulter les sites', 'module' => 'site', 'description' => 'Lister et consulter les sites'],
            ['code' => 'site.sites.create', 'libelle' => 'Créer un site', 'module' => 'site', 'description' => 'Créer un site'],
            ['code' => 'site.sites.update', 'libelle' => 'Modifier un site', 'module' => 'site', 'description' => 'Modifier un site existant'],
            ['code' => 'site.sites.delete', 'libelle' => 'Supprimer un site', 'module' => 'site', 'description' => 'Mettre un site à la corbeille'],
            ['code' => 'project.projects.view', 'libelle' => 'Consulter les projets', 'module' => 'project', 'description' => 'Lister et consulter les projets'],
            ['code' => 'project.projects.create', 'libelle' => 'Créer un projet', 'module' => 'project', 'description' => 'Créer un projet'],
            ['code' => 'project.projects.update', 'libelle' => 'Modifier un projet', 'module' => 'project', 'description' => 'Modifier un projet existant'],
            ['code' => 'project.projects.delete', 'libelle' => 'Supprimer un projet', 'module' => 'project', 'description' => 'Mettre un projet à la corbeille'],
            ['code' => 'project.sites.manage', 'libelle' => 'Gérer les sites du projet', 'module' => 'project', 'description' => 'Ajouter, modifier ou retirer des sites d\'un projet'],
            ['code' => 'project.events.create', 'libelle' => 'Créer un événement projet', 'module' => 'project', 'description' => 'Ajouter un événement à un projet'],
            ['code' => 'employee.employees.view', 'libelle' => 'Consulter les employés', 'module' => 'employee', 'description' => 'Lister et consulter les employés'],
            ['code' => 'employee.employees.create', 'libelle' => 'Créer un employé', 'module' => 'employee', 'description' => 'Créer un employé'],
            ['code' => 'employee.employees.update', 'libelle' => 'Modifier un employé', 'module' => 'employee', 'description' => 'Modifier un employé existant'],
            ['code' => 'employee.employees.delete', 'libelle' => 'Supprimer un employé', 'module' => 'employee', 'description' => 'Mettre un employé à la corbeille'],
            ['code' => 'task.tasks.view', 'libelle' => 'Consulter les tâches', 'module' => 'task', 'description' => 'Lister et consulter les tâches'],
            ['code' => 'task.tasks.create', 'libelle' => 'Créer une tâche', 'module' => 'task', 'description' => 'Créer une tâche'],
            ['code' => 'task.tasks.update', 'libelle' => 'Modifier une tâche', 'module' => 'task', 'description' => 'Modifier une tâche existante'],
            ['code' => 'task.tasks.delete', 'libelle' => 'Supprimer une tâche', 'module' => 'task', 'description' => 'Mettre une tâche à la corbeille'],
            ['code' => 'finance.invoices.view', 'libelle' => 'Consulter les factures', 'module' => 'finance', 'description' => 'Lister et consulter les factures'],
            ['code' => 'finance.invoices.create', 'libelle' => 'Créer une facture', 'module' => 'finance', 'description' => 'Créer une facture'],
            ['code' => 'finance.invoices.update', 'libelle' => 'Modifier une facture', 'module' => 'finance', 'description' => 'Modifier une facture existante'],
            ['code' => 'finance.invoices.delete', 'libelle' => 'Supprimer une facture', 'module' => 'finance', 'description' => 'Mettre une facture à la corbeille'],
            ['code' => 'finance.transactions.view', 'libelle' => 'Consulter les transactions', 'module' => 'finance', 'description' => 'Lister et consulter les transactions financières'],
            ['code' => 'finance.transactions.create', 'libelle' => 'Créer une transaction', 'module' => 'finance', 'description' => 'Créer une transaction financière'],
            ['code' => 'finance.transactions.update', 'libelle' => 'Modifier une transaction', 'module' => 'finance', 'description' => 'Modifier une transaction existante'],
            ['code' => 'finance.transactions.delete', 'libelle' => 'Supprimer une transaction', 'module' => 'finance', 'description' => 'Mettre une transaction à la corbeille'],
            ['code' => 'stock.equipment.view', 'libelle' => 'Consulter le matériel', 'module' => 'stock', 'description' => 'Lister et consulter le matériel'],
            ['code' => 'stock.equipment.create', 'libelle' => 'Créer un équipement', 'module' => 'stock', 'description' => 'Créer un équipement'],
            ['code' => 'stock.equipment.update', 'libelle' => 'Modifier un équipement', 'module' => 'stock', 'description' => 'Modifier un équipement existant'],
            ['code' => 'stock.equipment.delete', 'libelle' => 'Supprimer un équipement', 'module' => 'stock', 'description' => 'Mettre un équipement à la corbeille'],
            ['code' => 'stock.movements.view', 'libelle' => 'Consulter les mouvements de stock', 'module' => 'stock', 'description' => 'Lister et consulter les mouvements de stock'],
            ['code' => 'stock.movements.create', 'libelle' => 'Créer un mouvement de stock', 'module' => 'stock', 'description' => 'Créer un mouvement de stock'],
            ['code' => 'stock.movements.update', 'libelle' => 'Modifier un mouvement de stock', 'module' => 'stock', 'description' => 'Modifier un mouvement de stock existant'],
            ['code' => 'stock.movements.delete', 'libelle' => 'Supprimer un mouvement de stock', 'module' => 'stock', 'description' => 'Mettre un mouvement de stock à la corbeille'],
            ['code' => 'document.documents.view', 'libelle' => 'Consulter les documents', 'module' => 'document', 'description' => 'Lister les documents'],
            ['code' => 'document.documents.upload', 'libelle' => 'Téléverser un document', 'module' => 'document', 'description' => 'Téléverser un document'],
            ['code' => 'document.documents.delete', 'libelle' => 'Supprimer un document', 'module' => 'document', 'description' => 'Supprimer un document'],
            ['code' => 'configuration.settings.update', 'libelle' => 'Modifier les paramètres', 'module' => 'configuration', 'description' => 'Modifier les paramètres applicatifs'],
            ['code' => 'access.users.manage', 'libelle' => 'Gérer les utilisateurs', 'module' => 'access', 'description' => 'Créer, modifier et suspendre des utilisateurs'],
            ['code' => 'access.permissions.manage', 'libelle' => 'Gérer les permissions', 'module' => 'access', 'description' => 'Attribuer ou retirer des permissions individuelles'],
            ['code' => 'access.audit.view', 'libelle' => 'Consulter le journal d\'audit', 'module' => 'access', 'description' => 'Consulter l\'historique des actions'],
            ['code' => 'referentiel.devises.view', 'libelle' => 'Consulter les devises', 'module' => 'referentiel', 'description' => 'Lister et consulter les devises'],
            ['code' => 'referentiel.pays.view', 'libelle' => 'Consulter les pays', 'module' => 'referentiel', 'description' => 'Lister et consulter les pays'],
        ];
    }

    /** @return list<string> */
    public static function allCodes(): array
    {
        return array_column(self::all(), 'code');
    }

    /** @return array<string, list<string>> */
    public static function rolePermissions(): array
    {
        $agent = [
            'dashboard.view',
            'client.clients.view', 'client.clients.create', 'client.clients.update',
            'client.comments.create',
            'site.sites.view', 'site.sites.create', 'site.sites.update',
            'project.projects.view', 'project.projects.create', 'project.projects.update',
            'employee.employees.view',
            'task.tasks.view', 'task.tasks.create', 'task.tasks.update',
            'finance.invoices.view',
            'finance.transactions.view',
            'stock.equipment.view',
            'stock.movements.view',
            'document.documents.view', 'document.documents.upload',
            'referentiel.devises.view', 'referentiel.pays.view',
        ];

        $coordinateur = array_values(array_unique(array_merge($agent, [
            'client.clients.delete',
            'site.sites.delete',
            'project.projects.delete', 'project.sites.manage', 'project.events.create',
            'employee.employees.create', 'employee.employees.update', 'employee.employees.delete',
            'task.tasks.delete',
            'finance.invoices.create', 'finance.invoices.update', 'finance.invoices.delete',
            'finance.transactions.create', 'finance.transactions.update', 'finance.transactions.delete',
            'stock.equipment.create', 'stock.equipment.update', 'stock.equipment.delete',
            'stock.movements.create', 'stock.movements.update', 'stock.movements.delete',
            'document.documents.delete',
            'access.audit.view',
        ])));

        return [
            Role::ADMIN->value => self::allCodes(),
            Role::COORDINATEUR->value => $coordinateur,
            Role::AGENT->value => $agent,
        ];
    }
}

PHP);

w('DataFixtures/AppFixtures.php', <<<'PHP'
<?php

namespace App\DataFixtures;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Enum\Role;
use App\Referentiel\Application\Service\ReferentielBootstrapService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ReferentielBootstrapService $bootstrapService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->bootstrapService->bootstrap();

        $users = [
            ['Admin', 'ENT', '+237600000001', 'admin@ent.local', Role::ADMIN],
            ['Paul', 'Coordinateur', '+237600000002', 'coordinateur@ent.local', Role::COORDINATEUR],
            ['Jean', 'Agent', '+237600000003', 'agent@ent.local', Role::AGENT],
        ];

        foreach ($users as [$prenom, $nom, $telephone, $login, $role]) {
            $user = new Utilisateur($prenom, $nom, $telephone, $login, 'placeholder', $role);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, '123'));
            $manager->persist($user);
        }

        $manager->flush();
    }
}

PHP);

w('Configuration/Presentation/Api/Controller/CorbeilleController.php', <<<'PHP'
<?php

namespace App\Configuration\Presentation\Api\Controller;

use App\Client\Application\Command\RestoreClient\RestoreClientCommand;
use App\Client\Application\Command\RestoreClient\RestoreClientHandler;
use App\Client\Application\Query\ListDeletedClients\ListDeletedClientsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/corbeille')]
#[IsGranted('configuration.settings.update')]
final class CorbeilleController extends AbstractController
{
    #[Route('/clients', name: 'api_corbeille_clients_list', methods: ['GET'])]
    public function listClients(ListDeletedClientsHandler $handler): JsonResponse
    {
        return $this->json(['items' => $handler->handle()]);
    }

    #[Route('/clients/{id}/restore', name: 'api_corbeille_clients_restore', methods: ['POST'])]
    public function restoreClient(string $id, RestoreClientHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new RestoreClientCommand($id)));
    }
}

PHP);

w('Referentiel/Application/Service/ReferenceSettingsReader.php', <<<'PHP'
<?php

namespace App\Referentiel\Application\Service;

use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;

/**
 * @phpstan-type ReferenceFormatConfig array{prefixe: string, nbChiffres: int, titreRecu: string}
 */
final class ReferenceSettingsReader
{
    private const MIN_CHIFFRES = 1;
    private const MAX_CHIFFRES = 8;
    private const MAX_PREFIXE_LENGTH = 15;
    private const MAX_TITRE_LENGTH = 60;

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
    }

    /** @return ReferenceFormatConfig */
    public function lire(ReferenceSequenceType $type): array
    {
        $base = $type->settingPrefixKey();

        return [
            'prefixe' => $this->lirePrefixe($base),
            'nbChiffres' => $this->lireNbChiffres($base),
            'titreRecu' => $this->lireTitreRecu($base),
        ];
    }

    public function formater(ReferenceSequenceType $type, int $sequence): string
    {
        $config = $this->lire($type);

        return $config['prefixe'].str_pad((string) $sequence, $config['nbChiffres'], '0', STR_PAD_LEFT);
    }

    public function titreRecu(ReferenceSequenceType $type): string
    {
        return $this->lire($type)['titreRecu'];
    }

    public function titreRecuPourKind(string $kind): string
    {
        return 'RECU';
    }

    private function lirePrefixe(string $base): string
    {
        $value = $this->settingRepository->findByCle($base.'_PREFIXE')?->getValeur() ?? '';

        return mb_substr(trim($value), 0, self::MAX_PREFIXE_LENGTH);
    }

    private function lireNbChiffres(string $base): int
    {
        $raw = $this->settingRepository->findByCle($base.'_NB_CHIFFRES')?->getValeur() ?? '3';
        $value = (int) $raw;

        return max(self::MIN_CHIFFRES, min(self::MAX_CHIFFRES, $value));
    }

    private function lireTitreRecu(string $base): string
    {
        $value = trim($this->settingRepository->findByCle($base.'_TITRE_RECU')?->getValeur() ?? '');
        if ($value === '') {
            return 'RECU';
        }

        return mb_substr($value, 0, self::MAX_TITRE_LENGTH);
    }
}

PHP);

w('Dashboard/Application/Query/GetDashboardSummary/GetDashboardSummaryQuery.php', <<<'PHP'
<?php

namespace App\Dashboard\Application\Query\GetDashboardSummary;

final class GetDashboardSummaryQuery
{
}

PHP);

w('Dashboard/Application/Query/GetDashboardSummary/GetDashboardSummaryHandler.php', <<<'PHP'
<?php

namespace App\Dashboard\Application\Query\GetDashboardSummary;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Task\Domain\Repository\TaskRepositoryInterface;

final class GetDashboardSummaryHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(GetDashboardSummaryQuery $query = new GetDashboardSummaryQuery()): array
    {
        return [
            'activeProjects' => $this->projectRepository->countByStatus(ProjectStatus::ACTIVE),
            'tasksToday' => $this->taskRepository->countDueToday(),
            'clients' => $this->clientRepository->countEnabled(),
            'unpaidInvoices' => $this->invoiceRepository->countByStatus(InvoiceStatus::SENT),
        ];
    }
}

PHP);

w('Dashboard/Presentation/Api/Controller/DashboardController.php', <<<'PHP'
<?php

namespace App\Dashboard\Presentation\Api\Controller;

use App\Dashboard\Application\Query\GetDashboardSummary\GetDashboardSummaryHandler;
use App\Dashboard\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
final class DashboardController extends AbstractController
{
    #[Route('/summary', name: 'api_dashboard_summary', methods: ['GET'])]
    #[IsGranted('dashboard.view')]
    public function summary(GetDashboardSummaryHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetDashboardSummaryQuery()));
    }
}

PHP);

require __DIR__ . '/generate-ent-modules-part2.php';

echo "\n=== Total files created: " . count($created) . " ===\n";
