export const PROJECT_STATUS_OPTIONS = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'En attente', value: 'pending' },
  { label: 'Actif', value: 'active' },
  { label: 'Terminé', value: 'completed' },
  { label: 'Annulé', value: 'cancelled' },
]

export const PROJECT_SITE_STATUS_OPTIONS = [
  { label: 'En attente', value: 'pending' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminé', value: 'completed' },
  { label: 'Bloqué', value: 'blocked' },
]

export const TASK_STATUS_OPTIONS = [
  { label: 'En attente', value: 'pending' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminée', value: 'completed' },
  { label: 'Annulée', value: 'cancelled' },
]

export const INVOICE_STATUS_OPTIONS = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Devis', value: 'quote' },
  { label: 'Facturé', value: 'invoiced' },
]

export const STOCK_DIRECTION_OPTIONS = [
  { label: 'Entrée', value: 'in' },
  { label: 'Sortie', value: 'out' },
]

export const TRANSACTION_TYPE_OPTIONS = [
  { label: 'Recette', value: 'income' },
  { label: 'Dépense', value: 'expense' },
]

export const TRANSACTION_CATEGORY_OPTIONS = [
  { label: 'Paiement facture', value: 'InvoicePayment' },
  { label: 'Dépense projet', value: 'ProjetExpense' },
  { label: 'Dépense site', value: 'SiteExpense' },
  { label: 'Dépense matériel', value: 'MaterialExpense' },
  { label: 'Dépense équipement', value: 'EquipmentExpense' },
  { label: 'Autre dépense', value: 'OtherExpense' },
]

export const EXPENSE_CATEGORY_OPTIONS = TRANSACTION_CATEGORY_OPTIONS.filter((o) => o.value !== 'InvoicePayment')

export const TRANSACTION_STATUS_OPTIONS = [
  { label: 'En attente', value: 'pending' },
  { label: 'Terminée', value: 'completed' },
  { label: 'Annulée', value: 'cancelled' },
]

const projectStatusMap = Object.fromEntries(PROJECT_STATUS_OPTIONS.map((o) => [o.value, o.label]))
const projectSiteStatusMap = Object.fromEntries(PROJECT_SITE_STATUS_OPTIONS.map((o) => [o.value, o.label]))
const taskStatusMap = Object.fromEntries(TASK_STATUS_OPTIONS.map((o) => [o.value, o.label]))
const invoiceStatusMap = Object.fromEntries(INVOICE_STATUS_OPTIONS.map((o) => [o.value, o.label]))

export function projectStatusLabel(status) {
  return projectStatusMap[status] ?? status ?? '—'
}

export function projectSiteStatusLabel(status) {
  return projectSiteStatusMap[status] ?? status ?? '—'
}

export function taskStatusLabel(status) {
  return taskStatusMap[status] ?? status ?? '—'
}

export function invoiceStatusLabel(status) {
  return invoiceStatusMap[status] ?? status ?? '—'
}

export function projectStatusSeverity(status) {
  switch (status) {
    case 'active': return 'success'
    case 'pending': return 'warn'
    case 'completed': return 'info'
    case 'cancelled': return 'danger'
    default: return 'secondary'
  }
}

export function projectSiteStatusSeverity(status) {
  switch (status) {
    case 'in_progress': return 'warn'
    case 'completed': return 'success'
    case 'blocked': return 'danger'
    default: return 'secondary'
  }
}

export function taskStatusSeverity(status) {
  switch (status) {
    case 'in_progress': return 'warn'
    case 'completed': return 'success'
    case 'cancelled': return 'danger'
    default: return 'secondary'
  }
}

export function invoiceStatusSeverity(status) {
  switch (status) {
    case 'quote': return 'warn'
    case 'invoiced': return 'success'
    default: return 'secondary'
  }
}

export function stockDirectionLabel(direction) {
  return direction === 'out' ? 'Sortie' : 'Entrée'
}

export function stockDirectionSeverity(direction) {
  return direction === 'out' ? 'warn' : 'success'
}

const transactionTypeMap = Object.fromEntries(TRANSACTION_TYPE_OPTIONS.map((o) => [o.value, o.label]))
const transactionCategoryMap = Object.fromEntries(TRANSACTION_CATEGORY_OPTIONS.map((o) => [o.value, o.label]))
const transactionStatusMap = Object.fromEntries(TRANSACTION_STATUS_OPTIONS.map((o) => [o.value, o.label]))

export function transactionTypeLabel(type) {
  return transactionTypeMap[type] ?? type ?? '—'
}

export function transactionCategoryLabel(category) {
  return transactionCategoryMap[category] ?? category ?? '—'
}

export function transactionStatusLabel(status) {
  return transactionStatusMap[status] ?? status ?? '—'
}

export function transactionStatusSeverity(status) {
  switch (status) {
    case 'completed': return 'success'
    case 'cancelled': return 'danger'
    default: return 'warn'
  }
}

export function formatDateFr(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('fr-FR')
}

export function formatDateTimeFr(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('fr-FR')
}
