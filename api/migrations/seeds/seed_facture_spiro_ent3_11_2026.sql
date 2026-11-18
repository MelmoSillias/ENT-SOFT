-- Facture exemple extraite de Facture_ENT_TECHNOLOGY_V7_table_protegee.html.
-- SQLite uniquement. Appliquer les migrations, dont Version20261118120000, avant import.
-- Executer le fichier entier sur la base cible, avec arret a la premiere erreur.
-- UUID Doctrine SQLite : CAST(X'...' AS TEXT), comme les autres seeds du projet.
-- Proforma (quote), 16/11/2026, projet libre : Installation électrique.
-- 35 lignes : HT 5 136 000, TVA 18 % 924 480, TTC 6 060 480 FCFA.
-- Le numero sequentiel est alloue automatiquement ; le numero mensuel est ENT4/11-2026.
-- Aucun paiement, aucun changement des parametres d'impression ou de numerotation.
-- Reexecution : aucune modification de la facture importee, meme si elle a ete editee.
-- Si le numero mensuel appartient deja a une autre facture, l'import est annule.

PRAGMA foreign_keys = ON;
BEGIN IMMEDIATE;

CREATE TEMP TABLE seed_spiro_guard (ok INTEGER NOT NULL);
CREATE TEMP TRIGGER seed_spiro_guard_failure
BEFORE INSERT ON seed_spiro_guard
WHEN NEW.ok = 0
BEGIN
    SELECT RAISE(ROLLBACK, 'Import annule : ENT4/11-2026 existe deja ou le client Spiro est desactive.');
END;

INSERT INTO seed_spiro_guard (ok)
SELECT NOT EXISTS (
    SELECT 1 FROM invoices
    WHERE number_monthly = 'ENT4/11-2026'
      AND id <> CAST(X'9f83e012a6fb4d47a855b2d9c811c844' AS TEXT)
);

CREATE TEMP TABLE seed_spiro_context AS
SELECT
    CAST(X'9f83e012a6fb4d47a855b2d9c811c844' AS TEXT) AS invoice_id,
    NOT EXISTS (
        SELECT 1 FROM invoices
        WHERE id = CAST(X'9f83e012a6fb4d47a855b2d9c811c844' AS TEXT)
    ) AS is_new;

-- Reutilise le client par code ou par nom, sans changer ses coordonnees.
INSERT INTO clients (id, code, title, description, address, postal_box, city, created_at, updated_at, is_enabled)
SELECT CAST(X'd85a4dbcfe7b478aa4c6fbcf729f27da' AS TEXT),
       'CLI-0003', 'Spiro Mali Motors', NULL, NULL, NULL, NULL,
       datetime('now'), datetime('now'), 1
WHERE (SELECT is_new FROM seed_spiro_context) = 1
  AND NOT EXISTS (
      SELECT 1 FROM clients
      WHERE code = 'CLI-0003' OR title = 'Spiro Mali Motors' COLLATE NOCASE
  );

INSERT INTO seed_spiro_guard (ok)
SELECT (SELECT is_new FROM seed_spiro_context) = 0 OR EXISTS (
    SELECT 1 FROM clients
    WHERE (code = 'CLI-0003' OR title = 'Spiro Mali Motors' COLLATE NOCASE)
      AND is_enabled = 1
);

CREATE TEMP TABLE seed_spiro_lines (
    position INTEGER PRIMARY KEY,
    description TEXT NOT NULL,
    unit TEXT NOT NULL,
    quantity REAL NOT NULL,
    unit_price REAL NOT NULL
);

INSERT INTO seed_spiro_lines (position, description, unit, quantity, unit_price) VALUES
(0, 'Coffret 1m/1m', 'Unité', 2, 200000),
(1, 'Cerveau Relais Controle', 'Unité', 1, 50000),
(2, 'Disjoncteur MCCB 400 A', 'Unité', 2, 150000),
(3, 'Disjoncteur 4P 100 A', 'Unité', 16, 30000),
(4, 'Disjoncteurs 32 A', 'Unité', 4, 5000),
(5, 'Disjoncteur 63 A', 'Unité', 4, 6000),
(6, 'Câble 4x10', 'Mètre', 110, 5500),
(7, 'Câble 3x4', 'Mètre', 40, 4000),
(8, 'Câble 3x2.5', 'Mètre', 40, 1750),
(9, 'Câble 3x1.5', 'Mètre', 110, 900),
(10, 'Câble 1x240 m2', 'Mètre', 2, 35000),
(11, 'Câble 1x25 m2', 'Mètre', 50, 3000),
(12, 'Jeux de barres', 'Unité', 2, 100000),
(13, 'Accessoires', 'Lot', 1, 150000),
(14, 'Parafoudre AC', 'Unité', 1, 25000),
(15, 'Kit prise de terre', 'Lot', 1, 80000),
(16, 'Prises de courant', 'Lot', 4, 6000),
(17, 'Double allumage', 'Lot', 4, 3000),
(18, 'Lampe interne', 'Unité', 3, 8000),
(19, 'Lampe externe', 'Lot', 8, 6000),
(20, 'Cosses 35', 'Unité', 130, 1000),
(21, 'Cosses 240', 'Unité', 30, 3000),
(22, 'Ventilateur plafond encastré', 'Lot', 3, 40000),
(23, 'Extracteur 110', 'Lot', 2, 35000),
(24, 'Climatiseur 1,5 chevaux inverter', 'Lot', 2, 200000),
(25, 'Poubelle 60 litre', 'Lot', 1, 20000),
(26, 'Coffret Extincteur', 'Lot', 2, 30000),
(27, 'Extincteur 5kg', 'Lot', 2, 25000),
(28, 'Manoeuvre', 'Lot', 1, 500000),
(29, 'Bac a eau', 'Unité', 1, 30000),
(30, 'Détecteur de fumée', 'Unité', 2, 15000),
(31, 'Boîte de secours premier soins', 'Unité', 1, 40000),
(32, 'Gaine thermique', 'Unité', 5, 5000),
(33, 'Prise de puissance', 'Unité', 16, 17500),
(34, 'NVR, Baie pour caméra, 2 cameras câble etc....', 'lot', 1, 300000);

-- Alloue un numero au-dela du compteur et de toutes les factures deja presentes.
INSERT INTO invoices (
    id, number, number_monthly, date, amount, subtotal, vat_enabled, vat_rate,
    status, client_id, project_id, project_label, created_at, updated_at, is_enabled
)
SELECT context.invoice_id,
       CAST(MAX(
           COALESCE((SELECT derniere_valeur FROM reference_sequences WHERE type = 'INVOICE'), 0),
           COALESCE((SELECT MAX(CAST(number AS INTEGER)) FROM invoices), 0)
       ) + 1 AS TEXT),
       'ENT4/11-2026', '2026-11-16',
       totals.ht + ROUND(totals.ht * 18 / 100.0, 2), totals.ht, 1, 18,
       'quote',
       (SELECT id FROM clients
        WHERE (code = 'CLI-0003' OR title = 'Spiro Mali Motors' COLLATE NOCASE)
          AND is_enabled = 1
        ORDER BY CASE WHEN code = 'CLI-0003' THEN 0 ELSE 1 END, created_at, id
        LIMIT 1),
       NULL, 'Installation électrique', datetime('now'), datetime('now'), 1
FROM seed_spiro_context AS context
CROSS JOIN (SELECT SUM(ROUND(quantity * unit_price, 2)) AS ht FROM seed_spiro_lines) AS totals
WHERE context.is_new = 1;

-- Identifiants aleatoires UUID v4, stockes au format attendu par Doctrine.
INSERT INTO invoice_lines (id, invoice_id, position, description, unit, quantity, unit_price, amount)
SELECT CAST(
           randomblob(6) || X'4f' || randomblob(1) || X'8f' || randomblob(7)
       AS TEXT),
       context.invoice_id, line.position, line.description, line.unit,
       line.quantity, line.unit_price, ROUND(line.quantity * line.unit_price, 2)
FROM seed_spiro_lines AS line
CROSS JOIN seed_spiro_context AS context
WHERE context.is_new = 1;

-- Ne jamais faire reculer les compteurs existants.
INSERT INTO reference_sequences (type, derniere_valeur)
SELECT 'INVOICE', CAST(invoice.number AS INTEGER)
FROM invoices AS invoice
JOIN seed_spiro_context AS context ON context.invoice_id = invoice.id
WHERE context.is_new = 1
ON CONFLICT (type) DO UPDATE
SET derniere_valeur = MAX(reference_sequences.derniere_valeur, excluded.derniere_valeur);

INSERT INTO invoice_monthly_sequences (year_month, derniere_valeur)
SELECT '2026-11', 4 FROM seed_spiro_context WHERE is_new = 1
ON CONFLICT (year_month) DO UPDATE
SET derniere_valeur = MAX(invoice_monthly_sequences.derniere_valeur, excluded.derniere_valeur);

DROP TABLE seed_spiro_lines;
DROP TABLE seed_spiro_context;
DROP TRIGGER seed_spiro_guard_failure;
DROP TABLE seed_spiro_guard;
COMMIT;

-- Recapitulatif de la facture importee.
SELECT invoice.number AS numero_sequentiel, invoice.number_monthly AS numero_mensuel,
       invoice.date, client.title AS client, invoice.status,
       COUNT(line.id) AS nombre_lignes, invoice.subtotal AS total_ht,
       ROUND(invoice.subtotal * invoice.vat_rate / 100.0, 2) AS tva,
       invoice.amount AS total_ttc
FROM invoices AS invoice
JOIN clients AS client ON client.id = invoice.client_id
LEFT JOIN invoice_lines AS line ON line.invoice_id = invoice.id
WHERE invoice.id = CAST(X'9f83e012a6fb4d47a855b2d9c811c844' AS TEXT)
GROUP BY invoice.id;
