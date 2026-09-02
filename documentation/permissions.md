# Permissions ENT-SOFT

Format : `{module}.{resource}.{action}`

## Rôles MVP

| Rôle | Périmètre |
|------|-----------|
| ADMIN | Toutes les permissions |
| COORDINATEUR | Projets, sites, planning, RH, clients, stocks, factures (CRUD) |
| AGENT | Lecture + tâches assignées, consultation limitée |

## Catalogue (extrait)

- `dashboard.view`
- `client.clients.{view,create,update,delete}`
- `site.sites.{view,create,update,delete}`
- `project.projects.{view,create,update,delete}`
- `project.sites.manage`, `project.events.create`
- `employee.employees.{view,create,update,delete}`
- `task.tasks.{view,create,update,delete}`
- `finance.invoices.{view,create,update,delete}`
- `finance.transactions.{view,create,update,delete}`
- `stock.equipment.{view,create,update,delete}`
- `stock.movements.{view,create,update,delete}`
- `document.documents.{view,upload,delete}`
- `access.users.manage`, `access.permissions.manage`, `access.audit.view`
- `configuration.settings.update`

Source de vérité : `api/src/AccessAudit/Domain/PermissionCatalog.php`

## Utilisateurs fixtures

| Login | Mot de passe | Rôle |
|-------|--------------|------|
| admin | 123 | ADMIN |
| coordinateur | 123 | COORDINATEUR |
| agent | 123 | AGENT |
