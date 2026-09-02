# Conventions UI frontend ENT-SOFT

## Stack

- Vue 3 Composition API (`<script setup>`)
- PrimeVue 4 (Aura preset), Tailwind 4
- Pinia (auth, layout), Axios (`services/api.js`)

## Structure

```
frontend/src/
├── config/app.js          # Branding + navigation
├── views/{module}/        # Pages (orchestrateurs)
├── domains/{module}/      # Composants réutilisables + services
└── domains/shared/        # AppCrudDialog, AppTablePanelHeader, composables
```

## Pattern page liste

1. `Card` + `AppTablePanelHeader` (recherche, bouton créer)
2. `AppTableState` (loading / error / empty)
3. `DataTable` PrimeVue
4. `Dialog` pour création/édition avec `{Entity}FormFields`
5. Permissions : `usePermissions()` + `v-can`

## Pages MVP

| Route | View |
|-------|------|
| `/` | DashboardView |
| `/projects` | ProjectListView |
| `/projects/:id` | ProjectDetailView (onglets) |
| `/sites` | SiteListView |
| `/tasks` | TaskListView (table + calendrier) |
| `/clients` | ClientListView |
| `/clients/:id` | ClientDetailView |
| `/stock/equipments` | EquipmentListView |
| `/employees` | EmployeeListView |
| `/invoices` | InvoiceListView |
| `/users` | UserListView |
| `/configurations` | ConfigurationView |

## Services API

Un service par module sous `domains/{module}/services/` — ex. `projectService.js` appelle `/api/projects`.
