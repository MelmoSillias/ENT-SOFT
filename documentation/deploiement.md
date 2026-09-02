# Déploiement ENT-SOFT

## Prérequis

- Docker + Docker Compose
- PHP 8.2+ et Composer (développement local sans Docker)
- Node.js 20+ (frontend)

## Démarrage Docker

```powershell
cd Symfony&Vue
copy .env.docker.example .env
# Éditer .env : APP_SECRET, MYSQL_* passwords
docker compose up -d
```

Services :
- **mysql** — base `ent_soft`
- **api** — Symfony sur port interne 80
- **web** — nginx + frontend build (port 8080 par défaut)

## Développement local

### Backend

```powershell
cd Symfony&Vue/api
composer install
copy .env.example .env
# Configurer DATABASE_URL
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony server:start
```

### Frontend

```powershell
cd Symfony&Vue/frontend
npm install
npm run dev
```

## Variables d'environnement clés

| Variable | Description |
|----------|-------------|
| APP_SECRET | Secret Symfony |
| JWT_ACCESS_SECRET | Secret tokens bearer |
| DATABASE_URL | Connexion MySQL |
| MYSQL_DATABASE | `ent_soft` |
| VITE_API_URL | `/api` ou URL API complète |
| RUN_MIGRATIONS | `true` en Docker pour auto-migrate |
| RUN_LOAD_FIXTURES | `true` pour charger admin au premier démarrage |

## Compte admin initial

- Login : `admin`
- Mot de passe : `123`
