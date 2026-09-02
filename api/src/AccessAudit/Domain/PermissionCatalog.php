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
