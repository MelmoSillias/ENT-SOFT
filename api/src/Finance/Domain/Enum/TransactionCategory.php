<?php

namespace App\Finance\Domain\Enum;

/**
 * Catégories système connues + codes legacy.
 * Les dépenses utilisateur sont désormais des chaînes libres (settings FINANCE_CATEGORIES_DEPENSES).
 */
enum TransactionCategory: string
{
    case INVOICE_PAYMENT = 'InvoicePayment';
    case PRESTATION_PAYMENT = 'PrestationPayment';
    case PROJECT_EXPENSE = 'ProjetExpense';
    case SITE_EXPENSE = 'SiteExpense';
    case MATERIAL_EXPENSE = 'MaterialExpense';
    case EQUIPMENT_EXPENSE = 'EquipmentExpense';
    case OTHER_EXPENSE = 'OtherExpense';

    public static function isSystemGenerated(string $category): bool
    {
        return \in_array($category, [
            self::INVOICE_PAYMENT->value,
            self::PRESTATION_PAYMENT->value,
        ], true);
    }
}
