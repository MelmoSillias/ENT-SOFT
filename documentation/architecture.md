# Architecture ENT-SOFT

## Vue d'ensemble

ENT-SOFT est un **monolithe modulaire** Symfony 7 + Vue 3, calqué sur le projet Super_cargo.

```
ENT-SOFT/
├── Symfony&Vue/
│   ├── api/          # Backend Symfony (DDD + CQRS-lite)
│   └── frontend/     # SPA Vue 3 + PrimeVue 4 + Tailwind 4
├── documentation/
└── readme.md
```

## Modules backend (bounded contexts)

| Module | Rôle |
|--------|------|
| SharedKernel | Traits UUID, timestamps, validation, événements |
| IdentityAccess | Authentification bearer + refresh tokens |
| AccessAudit | Permissions, rôles, journal d'audit |
| Referentiel | Séquences de codes, paramètres bootstrap |
| Configuration | Settings applicatifs |
| Client | Clients ENT |
| Site | Sites indépendants |
| Project | Projets, ProjectSite, ProjectEvent |
| Employee | Ressources humaines |
| Task | Tâches et planning |
| Finance | Factures et transactions financières |
| Stock | Équipements et mouvements de stock |
| Document | Fichiers uploadés (polymorphe) |
| Dashboard | Statistiques agrégées |
| System | Health check |

## Couches DDD par module

```
{Module}/
├── Domain/          Entity, Repository interfaces, Enum, Exception
├── Application/     Command/, Query/, Dto/, Service/
├── Infrastructure/  Persistence/Doctrine/
└── Presentation/    Api/Controller/
```

## Règles inter-modules

- Références cross-module en **UUID sans FK Doctrine** (ex. `Project.clientId`)
- Enrichissement des DTO via handlers dédiés (`GetProjectDetailHandler`)
- Permissions `{module}.{resource}.{action}` sur controllers et routes frontend

## Stack

- PHP 8.2+, Symfony 7.4, Doctrine ORM 3, MySQL 8
- Vue 3.5, Vite 6, Pinia, PrimeVue 4, Axios
