<?php

namespace App\Finance\Domain\Enum;

enum TransactionCategory: string
{
    case INVOICE_PAYMENT = 'InvoicePayment';
    case PRESTATION_PAYMENT = 'PrestationPayment';
    case PROJECT_EXPENSE = 'ProjetExpense';
    case SITE_EXPENSE = 'SiteExpense';
    case MATERIAL_EXPENSE = 'MaterialExpense';
    case EQUIPMENT_EXPENSE = 'EquipmentExpense';
    case OTHER_EXPENSE = 'OtherExpense';
}
