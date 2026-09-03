# Modèle de données ENT-SOFT

## Entités principales

### Client
- `code`, `title`, `description`
- Commentaires via `ClientComment` (clientId, content)
- Contacts via `ClientContact` (clientId, name, phone)

### Site
- `code`, `title`, `description`
- `clientId` (UUID optionnel)

### Project
- `code`, `title`, `object`, `dateDebut`, `dateFin`
- `status` (draft, pending, active, completed, cancelled)
- `budget`, `clientId`
- `sitesInformations` (JSON — labels d'infos complémentaires)

### ProjectLot
- `projectId`, `code` (ex. LOT1), `title`
- Unicité `(projectId, code)`

### ProjectSite
- `projectId`, `siteId`, `lotId` (optionnel), `technicianId` (optionnel)
- `status`, `dateAdded`
- `informationsValues` (JSON), `employeeIds` (JSON)

### ProjectEvent
- `projectId`, `date`, `title`

### Employee
- `name`, `email`, `phone`, `address`, `function`
- `userId` (optionnel)
- Les techniciens de terrain sont des employés (`function = technicien`) liés via `ProjectSite.technicianId`

### Task
- `title`, `description`, `dateCreation`, `dateDue`
- `status`, `siteId`, `employeeId`

### Invoice
- `number`, `date`, `amount`, `status`
- `clientId`, `projectId` (optionnel)

### FinancialTransaction
- `date`, `amount`, `type` (income/expense)
- `category`, `description`, `status`
- `fromParty`, `toParty`
- Liens optionnels : `clientId`, `projectId`, `siteId`

### Equipment
- `code`, `title`, `description`, `clientId` (optionnel)

### StockMovement + StockMovementLine
- Mouvement : `date`, `quantity`, `unit`, liens client/projet/site
- Ligne : `equipmentId`, `quantity`

### Document
- `title`, `description`, `fileName`, `filePath`
- `ownerType` (client/project/site), `ownerId`

## Génération des codes

Codes lisibles générés via `CodeGeneratorService` et `ReferenceSequence` :
CLIENT, PROJECT, SITE, EQUIPMENT, INVOICE
