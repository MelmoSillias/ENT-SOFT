# Documents métier, avec ou sans projet

Les bons de livraison sont stockés dans `project_documents`, séparément des pièces jointes du module `Document`. Le type est stocké en chaîne et mappé par `ProjectDocumentType` (actuellement `delivery_note`). Ajouter un type nécessite d'étendre cet enum et son rendu, pas de créer une nouvelle table.

## Authentification et permissions

Authentification Bearer existante. Aucune nouvelle permission ni attribution implicite.

| Méthode | Endpoint | Permission | Succès |
| --- | --- | --- | --- |
| GET | `/api/project-documents` | `project.projects.view` | 200, tableau transversal |
| POST | `/api/project-documents` | `project.projects.update` | 201, document |
| PUT | `/api/project-documents/{id}` | `project.projects.update` | 200, document |
| DELETE | `/api/project-documents/{id}` | `project.projects.delete` | 204, sans corps |
| GET | `/api/projects/{projectId}/documents` | `project.projects.view` | 200, tableau de documents |
| POST | `/api/projects/{projectId}/documents` | `project.projects.update` | 201, document |
| PUT | `/api/projects/{projectId}/documents/{id}` | `project.projects.update` | 200, document |
| DELETE | `/api/projects/{projectId}/documents/{id}` | `project.projects.delete` | 204, sans corps |
| GET | `/api/projects/{projectId}/documents/{id}/print` | `project.projects.view` **et** `impression.documents.print` | 200, HTML ou PDF |
| GET | `/api/impressions/documents/delivery_note/{id}` | mêmes deux permissions | même rendu |

Les opérations transversales réutilisent exactement les permissions existantes, même sans projet : création et modification utilisent `project.projects.update`, pas `project.projects.create`. Aucune ACL utilisateur supplémentaire.

- `GET /api/project-documents` liste tous les documents sans projet ou liés à un projet actif. Filtres facultatifs : `?type=delivery_note` et `?projectId={UUID}` (combinables). Sans `projectId`, aucun filtre projet ; une valeur vide ou la chaîne `null` est invalide (400). Il n’existe pas de filtre réservé aux documents sans projet.
- `POST /api/project-documents` : `projectId` absent ou JSON `null` crée un document sans projet ; un UUID le rattache à un projet existant et actif.
- `PUT /api/project-documents/{id}` remplace le contenu et l’affectation : `projectId` absent ou JSON `null` détache le document ; un UUID permet de le rattacher ou de le transférer à un autre projet actif.
- `DELETE /api/project-documents/{id}` supprime définitivement un document accessible, sans exiger de projet.
- Les routes imbriquées restent strictement filtrées par le projet de l’URL : jamais de document sans projet ou d’un autre projet. Dans leur corps POST/PUT, `projectId` peut être omis ; s’il est présent, il doit être un UUID égal à celui de l’URL (`null` et toute autre valeur sont refusés, 400). Aucun transfert ni détachement via ces routes.

Les deux listes acceptent `?type=delivery_note`, renvoient un tableau JSON sans enveloppe et sont triées par date décroissante puis UUID décroissant. Pas de pagination ni d’endpoint GET individuel dans ce contrat.

Tout projet référencé doit exister et être actif (404 sinon). Un document d’un autre projet ou sans projet est inaccessible via les routes imbriquées (404), ainsi que via l’impression générique si un `projectId` incompatible est fourni. Un document lié à un projet désactivé reste inaccessible, y compris en modification/suppression/impression transversale, et est exclu de la liste transversale ; ses données sont conservées.

Erreurs : 401 sans authentification, 403 sans permission, 400 pour UUID ou données invalides (JSON `{ "error": "…" }`), 404 pour projet/document absent ou inaccessible. La validation métier commune reste identique pour les deux familles de routes.

## Corps POST et PUT

PUT remplace intégralement le contenu ; les mêmes champs métier sont requis qu’à la création. Exemple transversal sans projet :

```json
{
  "projectId": null,
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
  "projectId": null,
  "object": null,
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

`projectId` est toujours renvoyé (UUID ou `null`), y compris sur les routes imbriquées. `object` est toujours renvoyé (texte ou `null`). `clientTitle` est lu sur le client actuel, pas figé à la création. DELETE supprime définitivement le document. Un projet désactivé rend ses documents inaccessibles sans supprimer leurs données.

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
| `projectId` | UUID du projet | facultatif sur `/api/impressions/...` ; s’il est fourni, filtre strict (UUID vide/invalide : 400, projet absent/désactivé ou affectation incompatible : 404) |

Exemples : `/api/projects/{projectId}/documents/{id}/print?format=pdf&template=modern&page=a4&orientation=portrait&disposition=attachment` ; sans projet : `/api/impressions/documents/delivery_note/{id}?format=pdf&template=modern&disposition=attachment`. Sur la route générique, omettre `projectId` permet aussi d’imprimer un document lié à un projet actif ; le nom du projet provient alors du document. Sans affectation, le contexte de rendu fournit `projectName: null`.

HTML inline déclenche `window.print()` après chargement. HTML attachment ne l'ouvre pas. PDF est généré par le support Dompdf existant ; les formats CSV/Excel/Word ne font pas partie de ce contrat. Les paramètres hors liste sont refusés (400), pas silencieusement convertis en HTML.

- Classique : A4 portrait par défaut, tableau pleine largeur, bordures noires, colonnes 57/21/22 %, titre gris, client/numéro manuel/ENT, logos/date, en-têtes foncés, remplissage à dix lignes, zone vide 78/22 % et signatures client/ENT. Les longues listes continuent sur plusieurs pages.
- Moderne : reprend réellement les styles bleu/vert, le bandeau, le pied de page et la pagination navigateur du template existant `facture.html.twig`, malgré les noms inversés des templates factures. Aucun prix, total ou bloc de règlement ; signatures client et entreprise. Les factures existantes ne sont pas modifiées.
- Société : profil et logo via `ImpressionDocumentSupport::profile()` ; le logo configuré est affiché dans les deux modèles.
- Client : logo dédié `logoUrl`, géré depuis la fiche client. `POST /api/clients/{id}/logo` reçoit le champ multipart `file` ; `DELETE /api/clients/{id}/logo` retire le logo. Permission `client.clients.update`. Images PNG/JPEG/GIF/WebP, 2 Mo et 4096 × 4096 px maximum, stockées sous `/uploads/client-logos/`. Le fichier local est intégré en data URI pour HTML et PDF, sans récupération distante ni recherche dans les pièces jointes. Les anciennes pièces jointes ne sont pas automatiquement converties en logo.
- Les longues cellules sont fragmentées pour éviter leur perte dans Dompdf ; les lignes persistées et renvoyées par l'API restent inchangées.

## Déploiement et tests

Appliquer `php bin/console doctrine:migrations:migrate --no-interaction` avant utilisation. Migrations réversibles : `Version20261120120000` (documents), `Version20261120130000` (logo client) et `Version20261120131000` (objet BL nullable). Les documents existants conservent leur date et n’ont pas d’objet par défaut.

La migration `Version20261120132000` rend `project_id` nullable sans changer les affectations existantes ni supprimer l’index projet. Son rollback rétablit NOT NULL uniquement si aucun document sans projet n’existe ; sinon il refuse explicitement, sans suppression ni réaffectation automatique. Réaffecter ou supprimer ces documents avant un rollback.

Tests ciblés :

```sh
php vendor/phpunit/phpunit/phpunit tests/Unit/Project/ProjectDocumentTest.php tests/Unit/Impression/ProjectDocumentImpressionTest.php tests/Integration/ProjectDocumentApiTest.php tests/Integration/ProjectDocumentMigrationTest.php
```

Les tests HTTP utilisent SQLite en mémoire, le firewall Bearer et les permissions réelles ; ils ne modifient pas la base de l'application.
