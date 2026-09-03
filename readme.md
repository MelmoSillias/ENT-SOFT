# Projet ENT

## Présentation de l'entreprise ENT

Ent et une entreprise proposant des prestations d'installations d'équipements télécoms de panneaux solaires et traite avec des operateurs télécoms ou des particuliers pour l'installation de leur équipements.

les clients donnent diverses sites pour lesquels ils souhaitent des prestations donc on a plusieurs sites par projet.
les sites peuvent être enregistrés avec un code unique donné sans dependre du client.

chaque projet peut avoir des données différentes selon les exigences du client pour le projet.
mais tous sont composés de sites, ont un status, ont un budget, ont un date de début et de fin, ont un client assigné, ont un site assigné... et des commentaires et des documents associés, dépenses éffectuées durant le projet etc...

les sites dans les projets ont des statuts, des commentaires, des employées assignées, des dépenses éffectuées, des documents associés, des tâches et planning, des événements, des contacts, des coordonnées, des horaires, des photos, des vidéos, des documents, des états des lieux, des rapports, des factures, des paiements, des réclamations, des demandes de devis, des demandes de maintenance, des demandes de remplacement d'équipements, etc...

Les projets sont gérés par des coordonnées qui font un planning journaliers des employées pour les déplacements sur les sites des clients avec des possibles changements au cours des voyages

## Description du projet

Le projet consiste à créer une application web permettant de gérer les projets, clients, factures, stocks(matériels et équipements), etc...

## Technologies utilisées

- PHP - Symfony
- SQLite
- Vue.js

On partira d'une base de code existante pour la partie backend et frontend.
le code referentiel est le projet super-cargo qui a déjà des modules exploitables, le style de developpement souhaitée, les bonnes pratiques de code, et un modèle à suivre.

## Cahier des charges

### Fonctionnalités

- Gestion des projets (ajout, modification, suppression, etc...)
- Gestion des sites (ajout, modification, suppression, etc...)
- Gestion des planning (ajout, modification, suppression, etc...)
- Gestion Rh (ajout, modification, suppression, planning, etc...)
- Gestion des clients (ajout, modification, suppression, factures, etc...)
- Gestion des factures (ajout, modification, suppression, paiements, etc...)
- Gestion des stocks materiels et equipements (ajout, modification, suppression, etc...)
- Gestion des utilisateurs (ajout, modification, suppression, roles, permissions, etc...)

pour le MVP, on se limitera à la gestion des projets, sites, planning, équipes, clients, factures, stocks, utilisateurs.

## Modèle de données

### Entités (les entités seront écrites dans la documentation en francais mais les codes en anglais)

- Project : les projets pilotés par ENT
  - uuid : uuidv4 (uuidv4)
  - code : string (code unique pour le projet lisible et affichée dans l'interface utilisateur)
  - title : string (titre du projet)
  - objet : string (description du projet)
  - date_creation : date (date de creation du projet)
  - date_debut : date (date de début du projet)
  - date_fin : date (date de fin du projet)
  - status : string (status du projet)
  - budget : float (budget du projet)
  - client : Client (client assigné au projet)
  - sites : Site[] (liste des sites assignés au projet)
  - sitesInformations : string [] (liste des informations complémentaires des sites données ou requisent pour le projet)

- Site : les différents sites indépendants des projets.
  - uuid : uuidv4 (uuidv4)
  - code : string (code unique pour le site lisible et affichée dans l'interface utilisateur)
  - title : string (titre du site)
  - description : string (description du site)
  - date_creation : date (date de creation du site)
  - client : Client (client assigné au site optionnel)

- ProjectSite :
  - uuid : uuidv4 (uuidv4)
  - project : Project (projet assigné au site)
  - site : Site (site assigné au projet)
  - employees : Employee[] (liste des employées assignés au site)
  - date_added : date (date d'ajout du site dans le projet)
  - status : string (status du site dans le projet)
  - comments : string[] (liste des commentaires associés au site dans le projet)
  - informationsValues : string[] (liste des valeurs des informations complémentaires du site dans le projet)

- Client :
  - uuid : uuidv4 (uuidv4)
  - code : string (code unique pour le client lisible et affichée dans l'interface utilisateur)
  - title : string (titre du client)
  - description : string (description du client)
  - date_creation : date (date de creation du client)
  - projects : Project[] (liste des projets assignés au client)
  - comments : string[] (liste des commentaires associés au client)
  - documents : Document[] (liste des documents associés au client)
  - factures : Facture[] (liste des factures associées au client)
  - paiements : FinancialTransaction[] (liste des paiements associés au client)

- Document :
  - uuid : uuidv4 (uuidv4)
  - title : string (titre du document)
  - description : string (description du document)
  - file_name : string (nom du fichier du document)
  - file_path : string (chemin du fichier du document)

- Invoice :
  - uuid : uuidv4 (uuidv4)
  - number : string (numéro de la facture)
  - date : date (date de la facture)
  - amount : float (montant de la facture)
  - status : string (status de la facture)
  - client : Client (client assigné à la facture)
  - project : Project (projet assigné à la facture optionnel)

- Equipment :
  - uuid : uuidv4 (uuidv4)
  - code : string (code unique pour le matériel lisible et affichée dans l'interface utilisateur)
  - title : string (titre du matériel)
  - description : string (description du matériel)
  - date_creation : date (date de creation du matériel)
  - client : Client (client assigné au matériel optionnel)

- StockMovement :
  - uuid : uuidv4 (uuidv4)
  - date : date (date de la mouvement de stock)
  - quantity : int (quantité du mouvement de stock)
  - unit : string (unité du mouvement de stock)
  - client : Client (client assigné au mouvement de stock)
  - project : Project (projet assigné au mouvement de stock optionnel)
  - site : Site (site assigné au mouvement de stock optionnel)
  - materials : Materials[] (liste des matériels assignés au mouvement de stock)

- Employee :
  - uuid : uuidv4 (uuidv4)
  - name : string (nom de l'employé)
  - email : string (email de l'employé)
  - phone : string (phone de l'employé)
  - address : string (adresse de l'employé)
  - function : string (fonction de l'employé)

- Task :
  - uuid : uuidv4 (uuidv4)
  - title : string (titre de la tâche)
  - description : string (description de la tâche)
  - date_creation : date (date de creation de la tâche)
  - date_due : date (date d'échéance de la tâche)
  - status : string (status de la tâche)
  - site : Site (site assigné à la tâche)
  - employee : Employee (employé assigné à la tâche)

- FinancialTransaction :
  - uuid : uuidv4 (uuidv4)
  - date : date (date de la transaction)
  - amount : float (montant de la transaction)
  - type : string (type de la transaction: "income", "expense")
  - category : string (catégorie de la transaction: "InvoicePayment", "ProjetExpense", "SiteExpense", "MaterialExpense", "EquipmentExpense", "OtherExpense")
  - description : string (description de la transaction)
  - status : string (status de la transaction)
  - from : string / employee (de qui la transaction)
  - to : string / employee (à qui la transaction)

- ProjectEvent :
  - uuid : uuidv4 (uuidv4)
  - date : date (date de l'événement)
  - title : string (titre de l'événement)

## Interfaces utilisateurs

- Dashboard :
  Affiche des statitsiques rapides sur les projets, clients, tâches, etc... de facon rapide avec des actions rapides pour les tâches.
  Affichage selon les permissions de l'utilisateur.

- Projects :
  Affichage de la liste des projets avec les informations essentielles et les actions rapide actions disponibles pour le projet.
  Des boutons actions pour l'ajout de projet par Dialog primeVue.
  Une Sous Page permettant de voir les details du projet.

- Sites :
  Affichage de la liste des sites avec les informations essentielles et les actions rapide actions disponibles pour le site.
  Des boutons actions pour l'ajout de site par Dialog primeVue.

- Planning & Tasks
  Affichage de la liste des tâches au format table, format diagram et au format calendar avec diverses possibilités de filtrage et de tri.
  Des boutons actions pour l'ajout de tâche par Dialog primeVue.

- Clients :
  Affichage de la liste des clients avec les informations essentielles et les actions rapide actions disponibles pour le client.
  Des boutons actions pour l'ajout de client par Dialog primeVue.
  Une Sous Page permettant de voir les details du client.

- Materials & Equipments :
  Affichage de la liste des matériels et équipements avec les informations essentielles et les actions rapide actions disponibles pour le matériel ou l'équipement.
  Des boutons actions pour l'ajout de matériel ou d'équipement par Dialog primeVue.
  Une Sous Page permettant de voir les details du matériel ou l'équipement.

- RH :
  Affichage de la liste des employées avec les informations essentielles et les actions rapide actions disponibles pour l'employé.
  Des boutons actions pour l'ajout d'employé par Dialog primeVue.
  Une Sous Page permettant de voir les details de l'employé.

- Utilisateurs :
  Affichage de la liste des utilisateurs avec les informations essentielles et les actions rapide actions disponibles pour l'utilisateur.
  Des boutons actions pour l'ajout d'utilisateur par Dialog primeVue.
  Une Sous Page permettant de voir les details de l'utilisateur.

- Settings :

On va coder d'abord de facon complète le backend avec toutes les éléments de base dans la structure DDD existant, puis on va ajouter les interfaces utilisateurs avec les modifications nécessaires sur le backend (ajout de DTO de requête composée etc...).

on copiera la base de code du projet avec les éléments qui sont reutilisables (authentification, securité, et autres domaines reutilisables pour le backend et le pour le frontend le shell layout les compléments aux domaines gardés depuis le backend, les comportements ui etc...).

toujours documenter le code dans un dossier documentation.
