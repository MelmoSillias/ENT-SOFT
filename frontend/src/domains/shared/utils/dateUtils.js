export function toApiDate(value) {
  if (!value) return null
  const d = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(d.getTime())) return null
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/**
 * Convertit une valeur (Date ou string) en datetime ISO 8601 pour l'API, ou null.
 */
export function toApiDateTime(value) {
  if (!value) return null
  const d = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(d.getTime())) return null
  return d.toISOString()
}

/**
 * Parse un datetime ISO renvoyé par l'API en Date locale, ou null.
 */
export function parseApiDateTime(value) {
  if (!value) return null
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

export function parseApiDate(value) {
  if (!value) return null
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

export function startOfDay(date) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  return d
}

export function endOfDay(date) {
  const d = new Date(date)
  d.setHours(23, 59, 59, 999)
  return d
}

/**
 * Convertit une période [start, end] (DatePicker range) en params API { from, to } (ISO).
 * Retourne un objet vide si aucune période n'est sélectionnée.
 */
export function periodToApiParams(period) {
  const params = {}
  if (period?.[0]) params.from = startOfDay(period[0]).toISOString()
  if (period?.[1]) params.to = endOfDay(period[1]).toISOString()
  return params
}

/**
 * Retourne une période [start, end] couvrant les n derniers mois jusqu'à aujourd'hui.
 */
export function lastMonthsRange(n) {
  const end = new Date()
  const start = new Date()
  start.setMonth(start.getMonth() - n)
  return [startOfDay(start), endOfDay(end)]
}
