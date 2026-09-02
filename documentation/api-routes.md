# Routes API ENT-SOFT

Préfixe : `/api`

## Auth

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/login` | Connexion |
| POST | `/token/refresh` | Rafraîchir le token |
| GET | `/me` | Profil + permissions |

## Ressources MVP

| Ressource | Routes |
|-----------|--------|
| Clients | `GET/POST /clients`, `GET/PUT/DELETE /clients/{id}`, `GET /clients/{id}/detail` |
| Sites | `GET/POST /sites`, `GET/PUT/DELETE /sites/{id}` |
| Projects | `GET/POST /projects`, `GET/PUT/DELETE /projects/{id}`, `GET /projects/{id}/detail` |
| Project sites | `POST/PUT/DELETE /projects/{id}/sites/{siteId}` |
| Project events | `POST /projects/{id}/events` |
| Employees | `GET/POST /employees`, `GET/PUT/DELETE /employees/{id}` |
| Tasks | `GET/POST /tasks`, `GET/PUT/DELETE /tasks/{id}` (+ filtres query) |
| Invoices | `GET/POST /invoices`, `GET/PUT/DELETE /invoices/{id}` |
| Transactions | `GET/POST /financial-transactions`, `GET/PUT/DELETE /financial-transactions/{id}` |
| Equipment | `GET/POST /equipment`, `GET/PUT/DELETE /equipment/{id}` |
| Stock movements | `GET/POST /stock-movements`, `GET/PUT/DELETE /stock-movements/{id}` |
| Documents | `GET /documents`, `POST /documents/upload`, `DELETE /documents/{id}` |
| Dashboard | `GET /dashboard/summary` |
| Health | `GET /health` |

## Exemple — création client

```http
POST /api/clients
Content-Type: application/json
Authorization: Bearer {token}

{
  "title": "Orange Cameroun",
  "description": "Opérateur télécom"
}
```

Réponse : `ClientResponseDto` avec `code` auto-généré.
