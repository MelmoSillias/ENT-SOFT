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
 * Convertit une période [start, end] en params API { from, to } (ISO).
 * Conserve l'heure choisie. Les bornes sont ordonnées (la plus ancienne en from).
 * Retourne un objet vide si aucune période n'est sélectionnée.
 */
export function periodToApiParams(period) {
  const params = {}
  let from = period?.[0] ? new Date(period[0]) : null
  let to = period?.[1] ? new Date(period[1]) : null
  if (from && Number.isNaN(from.getTime())) from = null
  if (to && Number.isNaN(to.getTime())) to = null
  if (from && to && from.getTime() > to.getTime()) {
    ;[from, to] = [to, from]
  }
  if (from) params.from = from.toISOString()
  if (to) params.to = to.toISOString()
  return params
}

function parseLocalDate(value) {
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : new Date(value.getTime())
  if (value == null || value === '') return null
  const s = String(value).trim()
  const day = s.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (day) return new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3]))
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d
}

function hasClock(value) {
  if (value == null || value === '') return false
  if (value instanceof Date) {
    return value.getHours() !== 0 || value.getMinutes() !== 0 || value.getSeconds() !== 0 || value.getMilliseconds() !== 0
  }
  const s = String(value).trim()
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return false
  return /T|\d{1,2}:\d{2}/.test(s)
}

/**
 * Datetime affiché d'une colonne date : heure de la valeur, sinon heure de timeFrom (ex. createdAt).
 */
export function displayedDateTime(value, timeFrom = null) {
  const date = parseLocalDate(value)
  if (!date) return null
  if (hasClock(value) || !hasClock(timeFrom)) return date
  const time = timeFrom instanceof Date ? timeFrom : new Date(timeFrom)
  if (Number.isNaN(time.getTime())) return date
  date.setHours(time.getHours(), time.getMinutes(), time.getSeconds(), time.getMilliseconds())
  return date
}

/**
 * Vrai si la date affichée (date + heure) est dans la période choisie, bornes incluses.
 */
export function matchesDisplayedPeriod(item, period, dateField = 'date', timeField = 'createdAt') {
  if (!period?.[0] && !period?.[1]) return true
  const at = displayedDateTime(item?.[dateField], item?.[timeField] ?? item?.created_at ?? item?.dateCreation)
  if (!at) return false
  let from = period[0] ? new Date(period[0]) : null
  let to = period[1] ? new Date(period[1]) : null
  if (from && Number.isNaN(from.getTime())) from = null
  if (to && Number.isNaN(to.getTime())) to = null
  if (from && to && from.getTime() > to.getTime()) {
    ;[from, to] = [to, from]
  }
  if (from && at < from) return false
  if (to && at > to) return false
  return true
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

/**
 * Retourne une période [start, end] couvrant le mois calendaire en cours.
 */
export function currentMonthRange() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return [startOfDay(start), endOfDay(end)]
}
