<?php

namespace App\Referentiel\Domain\Catalog;

use App\Configuration\Domain\Enum\TypeValeur;

/**
 * Paramètres applicatifs essentiels ENT (bootstrap idempotent).
 *
 * @phpstan-type SettingDef array{cle: string, valeur: string, type: TypeValeur, description: string|null}
 */
final class SettingsCatalog
{
    /** @return list<SettingDef> */
    public static function bootstrapSettings(): array
    {
        return [
            ['cle' => 'AGENCE_NOM', 'valeur' => 'ENT', 'type' => TypeValeur::STRING, 'description' => 'Nom de l\'entreprise'],
            ['cle' => 'AGENCE_TELEPHONE', 'valeur' => '+237 600 000 000', 'type' => TypeValeur::STRING, 'description' => 'Téléphone de l\'entreprise'],
            ['cle' => 'AGENCE_ADRESSE', 'valeur' => '123 Avenue Principale', 'type' => TypeValeur::STRING, 'description' => 'Adresse de l\'entreprise'],
            ['cle' => 'AGENCE_VILLE', 'valeur' => 'Douala', 'type' => TypeValeur::STRING, 'description' => 'Ville de l\'entreprise'],
            ['cle' => 'AGENCE_EMAIL', 'valeur' => 'contact@ent.local', 'type' => TypeValeur::STRING, 'description' => 'Email de l\'entreprise'],
            ['cle' => 'AGENCE_SITE_WEB', 'valeur' => 'https://ent.local', 'type' => TypeValeur::STRING, 'description' => 'Site web de l\'entreprise'],
            ['cle' => 'AGENCE_LOGO_URL', 'valeur' => '', 'type' => TypeValeur::STRING, 'description' => 'Chemin/URL du logo affiché sur les documents'],
            ['cle' => 'REFERENCE_CLIENT_PREFIXE', 'valeur' => 'CLI-', 'type' => TypeValeur::STRING, 'description' => 'Préfixe des codes client'],
            ['cle' => 'REFERENCE_CLIENT_NB_CHIFFRES', 'valeur' => '4', 'type' => TypeValeur::INTEGER, 'description' => 'Nombre de chiffres du code client'],
            ['cle' => 'REFERENCE_CLIENT_TITRE_RECU', 'valeur' => 'CLIENT', 'type' => TypeValeur::STRING, 'description' => 'Libellé interne client'],
            ['cle' => 'REFERENCE_PROJECT_PREFIXE', 'valeur' => 'PRJ-', 'type' => TypeValeur::STRING, 'description' => 'Préfixe des codes projet'],
            ['cle' => 'REFERENCE_PROJECT_NB_CHIFFRES', 'valeur' => '4', 'type' => TypeValeur::INTEGER, 'description' => 'Nombre de chiffres du code projet'],
            ['cle' => 'REFERENCE_PROJECT_TITRE_RECU', 'valeur' => 'PROJET', 'type' => TypeValeur::STRING, 'description' => 'Libellé interne projet'],
            ['cle' => 'REFERENCE_SITE_PREFIXE', 'valeur' => 'SIT-', 'type' => TypeValeur::STRING, 'description' => 'Préfixe des codes site'],
            ['cle' => 'REFERENCE_SITE_NB_CHIFFRES', 'valeur' => '4', 'type' => TypeValeur::INTEGER, 'description' => 'Nombre de chiffres du code site'],
            ['cle' => 'REFERENCE_SITE_TITRE_RECU', 'valeur' => 'SITE', 'type' => TypeValeur::STRING, 'description' => 'Libellé interne site'],
            ['cle' => 'REFERENCE_EQUIPMENT_PREFIXE', 'valeur' => 'EQP-', 'type' => TypeValeur::STRING, 'description' => 'Préfixe des codes équipement'],
            ['cle' => 'REFERENCE_EQUIPMENT_NB_CHIFFRES', 'valeur' => '4', 'type' => TypeValeur::INTEGER, 'description' => 'Nombre de chiffres du code équipement'],
            ['cle' => 'REFERENCE_EQUIPMENT_TITRE_RECU', 'valeur' => 'EQUIPEMENT', 'type' => TypeValeur::STRING, 'description' => 'Libellé interne équipement'],
            ['cle' => 'REFERENCE_INVOICE_PREFIXE', 'valeur' => 'FAC-', 'type' => TypeValeur::STRING, 'description' => 'Préfixe des numéros de facture'],
            ['cle' => 'REFERENCE_INVOICE_NB_CHIFFRES', 'valeur' => '5', 'type' => TypeValeur::INTEGER, 'description' => 'Nombre de chiffres du numéro de facture'],
            ['cle' => 'REFERENCE_INVOICE_TITRE_RECU', 'valeur' => 'FACTURE', 'type' => TypeValeur::STRING, 'description' => 'Libellé interne facture'],
            ['cle' => 'IMPRESSION_FOOTER_TEXT', 'valeur' => '', 'type' => TypeValeur::STRING, 'description' => 'Pied de page des documents'],
            ['cle' => 'IMPRESSION_SHOW_LOGO', 'valeur' => 'true', 'type' => TypeValeur::STRING, 'description' => 'Afficher le logo sur les documents'],
            ['cle' => 'IMPRESSION_MARGIN_MM', 'valeur' => '10', 'type' => TypeValeur::INTEGER, 'description' => 'Marges d\'impression (mm)'],
            ['cle' => 'IMPRESSION_DEFAULT_EXPORT_FORMAT', 'valeur' => 'pdf', 'type' => TypeValeur::STRING, 'description' => 'Format d\'export par défaut'],
            ['cle' => 'IMPRESSION_PAGE_TABLE', 'valeur' => 'a4', 'type' => TypeValeur::STRING, 'description' => 'Format page tableaux'],
            ['cle' => 'IMPRESSION_ORIENTATION_TABLE', 'valeur' => 'portrait', 'type' => TypeValeur::STRING, 'description' => 'Orientation tableaux'],
            ['cle' => 'IMPRESSION_PAGE_INVOICE', 'valeur' => 'a4', 'type' => TypeValeur::STRING, 'description' => 'Format page facture'],
            ['cle' => 'IMPRESSION_ORIENTATION_INVOICE', 'valeur' => 'portrait', 'type' => TypeValeur::STRING, 'description' => 'Orientation facture'],
        ];
    }
}
