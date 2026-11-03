# Import de données — format ENT-SOFT v1

L’écran **Paramètres → Import de données** permet de télécharger un modèle, de contrôler un fichier, puis d’enregistrer les lignes du lot. Les types proposés correspondent aux droits de création de l’utilisateur. L’ajout de justificatifs nécessite aussi `document.documents.upload`.

## Règles communes

- Un fichier contient un seul type de données, choisi dans l’écran.
- CSV encodé en **UTF-8**, avec ou sans BOM ; séparateur **point-virgule** `;` ; première ligne contenant les noms exacts des colonnes.
- Les colonnes peuvent être réordonnées. Les colonnes facultatives peuvent être absentes. Les colonnes inconnues ou en double sont refusées pour éviter de perdre des données.
- Une cellule contenant un point-virgule, un retour à la ligne ou des guillemets doit être entourée de guillemets doubles ; un guillemet intérieur s’écrit `""`.
- Les espaces de début et de fin de cellule sont retirés. Une cellule vide signifie « non renseigné ».
- Les dates utilisent `AAAA-MM-JJ`, par exemple `2026-11-03`. Les dates impossibles sont refusées.
- Les montants sont strictement positifs, inférieurs à 1 000 milliards, sans symbole monétaire ni séparateur de milliers : `12500`, `12500.50` ou `12500,50`. Ils utilisent la devise habituelle du logiciel ; aucune conversion n’est effectuée.
- `id_import` est obligatoire pour chaque ligne : 1 à 100 lettres ASCII, chiffres, points, tirets ou underscores. Exemple : `DEP-2026-001`. Il doit être **stable et unique par type**, y compris entre plusieurs fichiers. Utilisez des préfixes par source si nécessaire.
- Réimporter une ligne identique avec le même `id_import` l’ignore, justificatifs compris. Un même identifiant avec un contenu différent est refusé. Les fichiers des justificatifs doivent aussi être identiques. Les modifications se font dans la fiche existante.
- Cette protection concerne les lignes créées par cet import. Donner un nouvel `id_import` à une dépense déjà saisie manuellement ne permet pas de détecter qu’il s’agit de la même opération.
- Un lot est limité à **500 lignes**, son CSV à **2 Mo**, le fichier envoyé à **20 Mo**. Chaque justificatif est limité à **10 Mo** ; le ZIP à **50 Mo décompressés** et **1 000 entrées**.

Dans Excel, conservez les codes, identifiants et téléphones comme texte, puis exportez en CSV UTF-8 avec `;`. Vérifiez les dates après export : certains réglages régionaux produisent des dates françaises ou des virgules comme séparateurs de colonnes.

## Relations entre les données

Les cellules `client_code` et `site_code` acceptent le code exact d’une fiche existante et active. `prestataire_email` accepte l’email exact d’un prestataire actif. Une référence ambiguë est refusée.

Ces trois colonnes acceptent également **`@id_import`**, faisant référence à une fiche créée lors d’un import précédent du type correspondant. Par exemple, `client_code=@CLI-001` désigne le client importé avec `id_import=CLI-001`. Cela permet de préparer les fichiers avant de connaître les codes générés par le logiciel.

Ordre conseillé : **clients → prestataires → sites → équipements → prestations → dépenses/revenus**. Chaque lot doit être enregistré avant de contrôler les lots qui s’y réfèrent. Les références ne créent jamais automatiquement une fiche manquante.

## Colonnes par type

| Type | Colonnes obligatoires | Colonnes facultatives |
| --- | --- | --- |
| Clients | `id_import`, `titre` | `description`, `adresse`, `boite_postale`, `ville` |
| Sites | `id_import`, `code`, `titre` | `description`, `client_code`, `latitude`, `longitude` |
| Prestataires | `id_import`, `prenom`, `nom`, `email`, `telephone` | `adresse` |
| Prestations | `id_import`, `prestataire_email`, `description`, `montant`, `date` | `site_code`, `statut_travail` |
| Dépenses | `id_import`, `date`, `montant`, `categorie`, `emetteur`, `beneficiaire` | `description`, `client_code`, `site_code`, `pieces_jointes` |
| Revenus | `id_import`, `date`, `montant`, `categorie`, `emetteur`, `beneficiaire` | `description`, `client_code`, `site_code`, `statut`, `pieces_jointes` |
| Équipements | `id_import`, `titre` | `description`, `unite`, `client_code` |

### Clients, sites et prestataires

Le code client est généré automatiquement ; le code site est fourni et doit être unique, même vis-à-vis des sites désactivés. Un code site est limité à 50 caractères.

Les coordonnées sont des degrés décimaux, latitude entre -90 et 90, longitude entre -180 et 180. Elles sont renseignées ensemble ou toutes deux vides.

Les emails des prestataires doivent être valides et ne pas déjà exister. Les téléphones acceptent un `+` initial facultatif, des chiffres et des espaces simples, avec au moins 6 chiffres et 20 caractères maximum.

### Prestations

`statut_travail` : `pending`, `in_progress` ou `completed`. Valeur par défaut : `pending`. La prestation est initialement **non payée**. L’import ne déclenche aucun paiement.

### Dépenses et revenus

La catégorie est un texte libre non vide, limité à 100 caractères. Utilisez les libellés habituels du logiciel pour conserver des regroupements cohérents.

Les dépenses sont enregistrées avec le statut `completed`, conformément à la saisie normale. Pour les revenus, `statut` accepte `pending` (par défaut), `completed` ou `cancelled`.

Cet import ne crée pas de facture et n’enregistre pas de règlement de prestation. Les montants d’une prestation et d’une dépense constituent deux objets distincts ; le paiement d’une prestation doit suivre le fonctionnement habituel du logiciel.

### Équipements

Le code est généré automatiquement. `unite` accepte `unit` (par défaut), `lot`, `meter`, `m2`, `m3`, `kg`, `liter`, `piece`, `box`, `set`, `hour` ou `day`. Seule la fiche est créée : le stock initial est nul, aucun mouvement de stock n’est importé.

## Dépenses avec images ou documents

Envoyez un **ZIP** organisé ainsi, avec `donnees.csv` directement à sa racine :

```text
depenses-novembre.zip
├── donnees.csv
└── pieces/
    ├── recu-carburant.pdf
    └── photo-carburant.jpg
```

Contenu de `donnees.csv` :

```csv
id_import;date;montant;categorie;emetteur;beneficiaire;description;site_code;pieces_jointes
DEP-2026-001;2026-11-03;12500;Transport;Agence;Station-service;Carburant intervention;@SITE-001;pieces/recu-carburant.pdf|pieces/photo-carburant.jpg
DEP-2026-002;2026-11-03;5000;Fournitures;Agence;Quincaillerie;Petites fournitures;;
```

Le site `SITE-001` doit avoir été importé auparavant. Vous pouvez remplacer `@SITE-001` par son code réel ou laisser cette cellule vide.

- Plusieurs pièces sur la même ligne : séparez leurs chemins par **`|`**.
- Les chemins sont relatifs à la racine du ZIP, respectent les majuscules/minuscules et utilisent `/`.
- Formats acceptés : **PDF, JPG/JPEG, PNG, WEBP, DOCX, XLSX, TXT**. Le contenu doit correspondre à l’extension ; les exécutables et fichiers vides sont refusés.
- Les pièces doivent être dans `pieces/` ; les sous-dossiers y sont autorisés. Les chemins absolus, `..`, les antislashs, les archives chiffrées et les entrées en double sont refusés.
- Chaque pièce présente doit être référencée par au moins une ligne. Les fichiers inutilisés sont signalés pour éviter de les oublier.
- Le titre du document est son nom de fichier. Les pièces sont consultables dans les documents de la transaction créée.
- Sans pièce jointe, un simple CSV suffit. Le même mécanisme ZIP est disponible pour les revenus.

## Contrôle, enregistrement et bilan

Le contrôle n’écrit aucune donnée. Le tableau affiche pour chaque ligne son numéro, son identifiant d’import, son résultat, son nombre de justificatifs et, après création, son identifiant dans le logiciel.

L’enregistrement refait toutes les vérifications côté serveur. Une seule ligne invalide bloque tout le lot. Les lignes déjà importées sont ignorées. Si une erreur survient pendant l’enregistrement, la transaction du lot est annulée et les fichiers nouvellement écrits sont supprimés.

Un bilan CSV est téléchargeable après le contrôle et après l’import. Si la connexion est interrompue et que le résultat est inconnu, relancez le contrôle avec le même fichier et les mêmes identifiants : les lignes déjà enregistrées seront reconnues.

## Installation et API

La migration [Version20261103120000.php](../api/migrations/Version20261103120000.php) crée la table de suivi `data_import_records`. Appliquez les migrations avant d’utiliser l’écran :

```sh
cd api
php bin/console doctrine:migrations:migrate --no-interaction
```

Le déploiement Docker habituel applique les migrations au démarrage lorsque `RUN_MIGRATIONS=true`. Aucun nouveau droit n’est nécessaire : les imports réutilisent les droits de création et d’ajout de documents existants.

La configuration Docker fournie autorise 20 Mo de fichier et 25 Mo de requête multipart. Pour une installation différente, adaptez les limites nginx/PHP en conséquence. L’application renvoie un message si le fichier dépasse ses limites ou si le serveur refuse l’envoi.

- `GET /api/data-import/formats` : formats autorisés pour l’utilisateur.
- `GET /api/data-import/{type}/template` : modèle CSV vierge.
- `POST /api/data-import/{type}/preview` : formulaire multipart contenant `file`.
- `POST /api/data-import/{type}/execute` : même fichier et champ `digest` renvoyé au contrôle. Le serveur vérifie l’empreinte du fichier et revalide toutes les lignes.

Les réponses de contrôle/enregistrement contiennent `valid`, `ready`, `created`, `skipped`, `errors`, `digest` et `rows`. Un lot invalide lors de l’enregistrement retourne HTTP 422 ; un fichier différent du contrôle retourne HTTP 409. Le suivi des identifiants est conservé même si une fiche est ensuite désactivée : un réimport ne la réactive pas.
