# Déploiement ENT-SOFT

## Prérequis

- Docker + Docker Compose
- PHP 8.2+ avec `pdo_sqlite` et Composer (développement local sans Docker)
- Node.js 20+ (frontend)

## Démarrage Docker

```powershell
copy .env.docker.example .env
# Éditer .env : APP_SECRET (obligatoire)
docker compose up -d
```

Services :
- **entsoft-api** — Symfony + SQLite (`var/database/data.db` dans le volume `entsoft_sqlite_data`)
- **entsoft-web** — nginx + frontend build (port 8080 par défaut)

Plus de service MySQL : la base est un fichier SQLite persisté via le volume Docker `entsoft_sqlite_data`.

### Dokploy

Utiliser `docker-compose.dokploy.yml` (réseau externe `dokploy-network`).  
Les services sont préfixés `entsoft-*` pour éviter les collisions DNS avec d'autres stacks sur le même réseau.  
Variables minimales : `APP_SECRET`, optionnellement `RUN_LOAD_FIXTURES=true` au premier démarrage.  
Le volume `entsoft_sqlite_data` conserve la base entre redéploiements.

## Développement local

### Backend

```powershell
cd api
composer install
copy .env.example .env
# DATABASE_URL pointe déjà vers SQLite (var/data_dev.db)
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
# Optionnel — données Telecel :
# php bin/console dbal:run-sql -- "$(Get-Content migrations/seeds/seed_telecel_planning.sql -Raw)"
# ou : sqlite3 var/data_dev.db < migrations/seeds/seed_telecel_planning.sql
symfony server:start
```

### Frontend

```powershell
cd frontend
npm install
npm run dev
```

## Variables d'environnement clés

| Variable | Description |
|----------|-------------|
| APP_SECRET | Secret Symfony |
| JWT_ACCESS_SECRET | Secret tokens bearer |
| DATABASE_URL | `sqlite:////var/www/html/var/database/data.db` (Docker) ou chemin local |
| SQLITE_DATABASE_PATH | Chemin fichier SQLite (Docker, optionnel) |
| VITE_API_URL | `/api` ou URL API complète |
| API_UPSTREAM_HOST | Hôte Docker de l'API (`entsoft-api` par défaut) |
| RUN_MIGRATIONS | `true` en Docker pour auto-migrate |
| RUN_LOAD_FIXTURES | `true` pour charger admin au premier démarrage |

## Compte admin initial

- Login : `admin`
- Mot de passe : `123`
