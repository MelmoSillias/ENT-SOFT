# Documents métier de projet

Les bons de livraison sont stockés dans `project_documents`, séparément des pièces jointes du module `Document`. Le type est stocké en chaîne et mappé par `ProjectDocumentType` (actuellement `delivery_note`). Ajouter un type nécessite d'étendre cet enum et son rendu, pas de créer une nouvelle table.

## Authentification et permissions

Authentification Bearer existante. Aucune nouvelle permission ni attribution implicite.

| Méthode | Endpoint | Permission | Succès |
| --- | --- | --- | --- |
| GET | `/api/projects/{projectId}/documents` | `project.projects.view` | 200, tableau de documents |
| POST | `/api/projects/{projectId}/documents` | `project.projects.update` | 201, document |
| PUT | `/api/projects/{projectId}/documents/{id}` | `project.projects.update` | 200, document |
| DELETE | `/api/projects/{projectId}/documents/{id}` | `project.projects.delete` | 204, sans corps |
| GET | `/api/projects/{projectId}/documents/{id}/print` | `project.projects.view` **et** `impression.documents.print` | 200, HTML ou PDF |
| GET | `/api/impressions/documents/delivery_note/{id}?projectId={projectId}` | mêmes deux permissions | même rendu |

La création d'un document modifie un projet existant : elle utilise donc `project.projects.update`, pas `project.projects.create`. La liste accepte le filtre facultatif `?type=delivery_note`. Pas de pagination ni d'endpoint GET individuel dans ce contrat.

Le projet doit exister et être actif. Un document d'un autre projet est inaccessible via PUT, DELETE et les deux routes d'impression (404). Ces contrôles ne constituent pas une ACL par utilisateur : comme pour les projets existants, les autorisations sont celles du catalogue global.

## Corps POST et PUT

PUT remplace intégralement le contenu ; les mêmes champs sont requis qu'à la création.

```json
{
  "type": "delivery_note",
  "number": "BC-2026-042",
  "date": "2026-11-20",
  "clientId": "019a9e30-0000-7000-8000-000000000001",
  "lines": [
    { "description": "Routeur", "quantity": 2, "observation": "RAS" },
    { "description": "Câble réseau", "quantity": 12.5 }
  ]
}
```

- `number` : numéro **manuel** requis, chaîne non vide, 255 caractères maximum. Aucune génération automatique, pas d'unicité imposée. Ce numéro alimente aussi le champ « BON DE COMMANDE N° » du modèle classique ; aucun deuxième numéro implicite.
- `clientId` : UUID requis d'un client existant et actif ; peut différer du client principal du projet.
- `date` : date du BL au format strict `YYYY-MM-DD`, requise, modifiable dans le formulaire et affichée dans les deux modèles.
- `object` : objet facultatif, texte de 500 caractères maximum, nettoyé avec `trim`. Absent, vide ou nul : enregistré et renvoyé comme `null`. Un PUT sans objet l’efface. S’il est renseigné, il apparaît centré dans une ligne `colspan="3"` au-dessus des en-têtes du tableau, avec un bandeau bleu/vert adapté au modèle moderne.
- `lines` : liste non vide, ordre conservé ; chaque description est une chaîne non vide, 2000 caractères maximum ; quantité JSON numérique finie et strictement positive (pas de chaîne numérique) ; observation de 2000 caractères maximum, `RAS` si absente, nulle ou vide.
- Les chaînes du numéro, des descriptions et des observations sont nettoyées avec `trim`.

Réponse POST/PUT et éléments de la liste :

```json
{
  "id": "019a9e30-0000-7000-8000-000000000002",
  "type": "delivery_note",
  "number": "BC-2026-042",
  "date": "2026-11-20",
  "clientId": "019a9e30-0000-7000-8000-000000000001",
  "clientTitle": "Client livraison",
  "lines": [
    { "description": "Routeur", "quantity": 2, "observation": "RAS" }
  ]
}
```

`clientTitle` est lu sur le client actuel, pas figé à la création. DELETE supprime définitivement le document. Un projet désactivé rend ses documents inaccessibles sans supprimer leurs données.

Erreurs JSON `{ "error": "…" }` : 400 pour validation/UUID/paramètres invalides, 401 sans authentification valide, 403 si permission manquante, 404 si projet inexistant/désactivé ou document absent/hors projet.

## Impression

Les deux routes acceptent les mêmes paramètres :

| Paramètre | Valeurs | Défaut |
| --- | --- | --- |
| `format` | `html`, `pdf` | `html` |
| `page` | `a4`, `a5` | `a4` |
| `orientation` | `portrait`, `landscape` | `portrait` |
| `disposition` | `inline`, `attachment` | `inline` |
| `template` | `classic`, `modern` | setting existant `IMPRESSION_INVOICE_TEMPLATE` (sinon `classic`) |
| `projectId` | UUID du projet | **requis uniquement sur la route `/api/impressions/...`** |

Exemple : `/api/projects/{projectId}/documents/{id}/print?format=pdf&template=modern&page=a4&orientation=portrait&disposition=attachment`.

HTML inline déclenche `window.print()` après chargement. HTML attachment ne l'ouvre pas. PDF est généré par le support Dompdf existant ; les formats CSV/Excel/Word ne font pas partie de ce contrat. Les paramètres hors liste sont refusés (400), pas silencieusement convertis en HTML.

- Classique : A4 portrait par défaut, tableau pleine largeur, bordures noires, colonnes 57/21/22 %, titre gris, client/numéro manuel/ENT, logos/date, en-têtes foncés, remplissage à dix lignes, zone vide 78/22 % et signatures client/ENT. Les longues listes continuent sur plusieurs pages.
- Moderne : reprend réellement les styles bleu/vert, le bandeau, le pied de page et la pagination navigateur du template existant `facture.html.twig`, malgré les noms inversés des templates factures. Aucun prix, total ou bloc de règlement ; signatures client et entreprise. Les factures existantes ne sont pas modifiées.
- Société : profil et logo via `ImpressionDocumentSupport::profile()` ; le logo configuré est affiché dans les deux modèles.
- Client : logo dédié `logoUrl`, géré depuis la fiche client. `POST /api/clients/{id}/logo` reçoit le champ multipart `file` ; `DELETE /api/clients/{id}/logo` retire le logo. Permission `client.clients.update`. Images PNG/JPEG/GIF/WebP, 2 Mo et 4096 × 4096 px maximum, stockées sous `/uploads/client-logos/`. Le fichier local est intégré en data URI pour HTML et PDF, sans récupération distante ni recherche dans les pièces jointes. Les anciennes pièces jointes ne sont pas automatiquement converties en logo.
- Les longues cellules sont fragmentées pour éviter leur perte dans Dompdf ; les lignes persistées et renvoyées par l'API restent inchangées.

## Déploiement et tests

Appliquer `php bin/console doctrine:migrations:migrate --no-interaction` avant utilisation. Migrations réversibles : `Version20261120120000` (documents), `Version20261120130000` (logo client) et `Version20261120131000` (objet BL nullable). Les documents existants conservent leur date et n’ont pas d’objet par défaut.

Tests ciblés :

```sh
php vendor/phpunit/phpunit/phpunit tests/Unit/Project/ProjectDocumentTest.php tests/Unit/Impression/ProjectDocumentImpressionTest.php tests/Integration/ProjectDocumentApiTest.php tests/Integration/ProjectDocumentMigrationTest.php
```

Les tests HTTP utilisent SQLite en mémoire, le firewall Bearer et les permissions réelles ; ils ne modifient pas la base de l'application.
