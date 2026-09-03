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
  { label: 'Envoyée', value: 'sent' },
  { label: 'Payée', value: 'paid' },
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
    case 'sent': return 'warn'
    case 'paid': return 'success'
    case 'cancelled': return 'danger'
    default: return 'secondary'
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
