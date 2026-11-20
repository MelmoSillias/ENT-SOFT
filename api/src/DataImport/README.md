# DataImport

## Import de bons de livraison avec ou sans projet : `bons_livraison`

Le registre `/api/data-import/formats` expose `bons_livraison` avec la permission
`project.projects.update`. Les routes restent celles du système existant :

- `GET /api/data-import/bons_livraison/template` : modèle CSV, sans contexte requis ;
- `POST /api/data-import/bons_livraison/preview` : multipart `file`, `projectId` facultatif ;
- `POST /api/data-import/bons_livraison/execute` : multipart `file` + `digest` retourné par preview, même contexte `projectId` facultatif.

Sans champ `projectId` dans le multipart, les documents sont créés sans projet
(`projectId = null`). Si fourni, ce champ doit être l’UUID d’un projet existant
et actif, jamais une colonne CSV ni une propriété des lignes JSON. Une chaîne
vide, la chaîne littérale `null` ou un UUID invalide donnent 400 ; un projet
introuvable ou désactivé donne 404. Le contrôle est effectué en preview et à
nouveau en execute, y compris lors d’un réimport entièrement `skipped`.
La permission `project.projects.update` reste requise même sans projet (403
sinon). Aucun changement frontend n’est inclus.

Une ligne CSV représente un document. Seul le type métier `delivery_note` est
actuellement supporté : `bons_livraison` le sélectionne côté serveur. Le CSV
n’accepte ni `type` ni `projectId`. Les futurs types devront être enregistrés
explicitement avant d’être proposés à l’import. Le CSV existant
`id_import;numero;date;client_code;objet;lignes` reste compatible sans projet :
l’ordre des colonnes est libre et aucune colonne projet n’est nécessaire.

| Colonne | Obligatoire | Contrat |
| --- | --- | --- |
| `id_import` | Oui | 1 à 100 caractères `[A-Za-z0-9._-]`, unique dans le fichier et dans le couple projet/type. |
| `numero` | Oui | Numéro manuel non vide, 255 caractères maximum ; aucune numérotation automatique. |
| `date` | Oui | Date réelle au format `YYYY-MM-DD`. |
| `client_code` | Oui | Code d’un client actif existant ou `@id_import` d’un client déjà importé ; les références absentes, désactivées ou ambiguës sont refusées. |
| `lignes` | Oui | Liste JSON non vide, 100 000 caractères maximum. |
| `objet` | Non | Texte de 500 caractères maximum ; vide devient `null`. |

Chaque objet de `lignes` contient uniquement `description` (texte non vide,
2 000 caractères maximum), `quantity` (nombre JSON fini strictement positif,
**pas une chaîne**), et éventuellement `observation` (texte, 2 000 caractères
maximum ; défaut `RAS` si absente ou vide). Les clés inconnues sont refusées.
Les validations et normalisations métier sont celles de `ProjectDocument` ; le
preview construit une entité détachée sans écrire. Execute appelle
`ProjectDocumentService::save` avec projet, ou `saveGlobal` sans projet, avec les
mêmes contrôles projet/client/date et les mêmes règles de création que la saisie
manuelle. Le service n’est pas modifié.

```csv
id_import;numero;date;client_code;lignes;objet
BL001;BL-MANUEL-42;2026-11-20;@C001;"[{""description"":""Routeur"",""quantity"":2.5},{""description"":""Câble"",""quantity"":1,""observation"":""Livré""}]";Livraison du site
```

Le rapport preview/execute est inchangé (`data` contient les cellules CSV,
`attachments` vaut 0). Les limites existantes s’appliquent : UTF-8, séparateur
`;`, 2 Mo, 500 documents, aucune pièce jointe pour ce type.

### Contexte, idempotence et atomicité

- Pour `bons_livraison`, le `digest` est opaque et dépend du fichier, du type et
  de l’UUID canonique du projet ou du contexte sans projet (`null`). Ne pas le
  recalculer côté UI. Un changement de fichier ou de contexte (y compris ajout
  ou retrait du projet) impose un nouveau preview ; sinon execute répond 409.
  Le digest des autres types n’est pas modifié. Ce contrôle ne remplace ni
  l’autorisation ni les validations, réexécutées côté serveur.
- L’idempotence est isolée par type et contexte : sans projet, ou dans chaque
  projet. Le même `id_import` est autorisé sans projet et dans plusieurs projets.
  Dans le même contexte/type : même contenu → `skipped`, contenu modifié → erreur,
  aucune mise à jour. Les clés et empreintes des imports existants restent inchangées.
- Une ligne invalide bloque tout le fichier (execute : 422). Une erreur pendant
  l’écriture provoque le rollback complet, y compris les documents déjà flushés
  par le service et les enregistrements d’idempotence. Aucun document financier,
  client ou projet n’est créé par cet import.

### Tests

Depuis `api/` (base SQLite en mémoire isolée) :

```sh
php bin/phpunit tests/Unit/DataImport tests/Integration/DataImportTest.php tests/Integration/ProjectDocumentDataImportTest.php
```

Couverture : modèle et permission, validation métier sans écriture, références
client par code et `@id_import`, import sans projet avec le CSV existant,
idempotence par contexte/type, digest avec ajout/retrait/changement de projet,
projet fourni invalide/désactivé, CSV ne pouvant imposer un projet, lignes JSON
incorrectes, doublons et rollback du fichier après échec en base avec ou sans projet.

## Import de factures

Le type `factures` est exposé par les routes existantes de `/api/data-import` :
`GET /formats`, `GET /factures/template`, `POST /factures/preview` et
`POST /factures/execute`. Permission : `finance.invoices.create`.

## Format CSV

Une ligne CSV représente **une facture**, avec une ou plusieurs lignes métier.
CSV UTF-8 (BOM accepté), séparateur `;`, 2 Mo et 500 factures maximum.
Les colonnes optionnelles peuvent être omises ou laissées vides.

| Colonne | Obligatoire | Contrat / défaut |
| --- | --- | --- |
| `id_import` | Oui | Identifiant stable, 1 à 100 caractères `[A-Za-z0-9._-]`, unique dans le fichier et pour ce type d'import. |
| `date` | Oui | Date réelle au format `AAAA-MM-JJ`. |
| `client_code` | Oui | Code d'un client actif existant, ou `@id_import` d'un client déjà importé. Une référence ambiguë est refusée. Importer les clients d'abord. |
| `lignes` | Oui | Liste JSON non vide d'objets décrits ci-dessous ; 100 000 caractères maximum. |
| `statut` | Non | `draft` (défaut), `quote` ou `invoiced`. Ce n'est pas un statut de paiement. |
| `projet_libelle` | Non | Libellé libre, 255 caractères maximum ; ne crée ni ne lie une entité Project. |
| `tva_active` | Non | `true` / `1` ou `false` / `0` ; défaut `false`. |
| `taux_tva` | Non | Nombre de 0 à 100 inclus ; défaut 18, même si la TVA est désactivée. Point ou virgule décimale. |
| `conditions_paiement` | Non | Liste JSON de chaînes ; défaut `[]`, 10 000 caractères maximum. Espaces périphériques et conditions vides supprimés par le validateur du domaine. |

Chaque objet de `lignes` contient :

- `description` : chaîne non vide, 255 caractères maximum ;
- `quantity` : nombre strictement positif ;
- `unitPrice` : nombre, zéro et valeurs négatives acceptés comme dans le domaine Invoice (ligne de remise, par exemple) ;
- `unit` : chaîne optionnelle de 50 caractères maximum, défaut `Lot` si absente ou vide.

Les clés inconnues sont refusées pour éviter de perdre silencieusement des données.
Les nombres peuvent être des nombres JSON ou des chaînes décimales (dont `"2,5"`),
sans séparateur de milliers. Les valeurs non finies et débordements des montants
sont refusés avant exécution.

Exemple complet (les guillemets JSON sont doublés dans les cellules CSV) :

```csv
id_import;date;client_code;lignes;statut;projet_libelle;tva_active;taux_tva;conditions_paiement
F001;2026-11-20;@C001;"[{""description"":""Installation"",""quantity"":2,""unitPrice"":100,""unit"":""Lot""},{""description"":""Matériel"",""quantity"":1,""unitPrice"":50}]";invoiced;Réseau;true;18;"[""50 % à la commande"",""Solde à livraison""]"
```

Les numéros séquentiel et mensuel sont générés par `CreateInvoiceHandler` et
`InvoiceNumberGenerator`, jamais fournis dans le CSV. Le handler et
`InvoiceLineWriter` calculent les montants : chaque ligne est arrondie à deux
décimales, le HT est leur somme, la TVA est arrondie à deux décimales puis ajoutée
au HT. L'exemple produit 250 HT, 45 de TVA et 295 TTC.

Ni paiement ni transaction financière ne sont créés. Les pièces jointes ne sont
pas prises en charge pour ce type : pas de colonne `pieces_jointes` ; un ZIP avec
des pièces non référencées est refusé selon les règles existantes.

## Contrat preview / execute

Le rapport conserve ses champs existants, pour tous les types :
`digest` (ajouté par le contrôleur), `valid`, `created`, `ready`, `skipped`,
`errors`, `rows`. Chaque élément de `rows` contient `line`, `idImport`, `status`,
`message`, `entityId`, `attachments` (toujours 0 pour les factures).
`line` est le numéro de ligne physique CSV, y compris les cellules multilignes.
Chaque élément de `rows` expose aussi `data`, un objet colonne CSV → valeur
chaîne, pour afficher l'aperçu dans le wizard. Il contient la ligne issue du
parseur CSV (espaces périphériques supprimés, colonnes optionnelles absentes
complétées par `""`), pas la commande métier normalisée : les cellules JSON
restent des chaînes. Ce champ est présent en preview et execute, quel que soit
le statut de la ligne (`ready`, `created`, `skipped` ou `error`). Les autres champs
et leur signification ne changent pas.

- Le preview prépare et valide sans écriture ni consommation de numéros.
- Execute attend le même fichier et son `digest` ; un fichier changé donne 409.
- Une ligne invalide bloque tout le lot ; execute renvoie 422 et aucune création.
- Même `id_import` + même contenu : `skipped`. Contenu différent : erreur, sans mise à jour.
  L'empreinte repose sur les cellules CSV, pas sur l'équivalence sémantique du JSON.
- En cas d'échec pendant la création, la transaction annule factures, lignes,
  numérotation et nouveaux enregistrements d'import du lot.
