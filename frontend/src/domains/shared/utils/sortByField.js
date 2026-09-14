import { displayedDateTime } from '@/domains/shared/utils/dateUtils'

const DATE_FIELD_NAMES = new Set([
  'date',
  'dateDue',
  'dateDebut',
  'dateFin',
  'date_action',
  'dateAction',
  'createdAt',
  'updatedAt',
  'recordedAt',
  'date_modification',
  'dateCreation',
])

/** Sort field used by DataTable so date columns include the displayed hour. */
export function tableSortField(sortField) {
  if (!sortField) return undefined
  return DATE_FIELD_NAMES.has(sortField) ? `${sortField}__at` : sortField
}

/**
 * Sort a list by field / order (1 asc, -1 desc).
 * Date fields include the displayed hour and follow the chosen order.
 * Equal primary values are ordered by creation time (then id) ascending.
 * @template T
 * @param {T[]} items
 * @param {string|null|undefined} sortField
 * @param {number} [sortOrder=1]
 * @returns {T[]}
 */
export function sortByField(items, sortField, sortOrder = 1) {
  if (!sortField || !items?.length) return items ?? []
  const order = sortOrder === -1 ? -1 : 1
  const prepared = items.map(withDateSortKeys)
  const dateField = prepared.some((item) => isDateLike(item?.[sortField]))
  return prepared.sort((a, b) => {
    const primary = dateField
      ? compareDateField(a, b, sortField)
      : compareValues(a?.[sortField], b?.[sortField])
    if (primary !== 0) return primary * order
    return compareCreationOrder(a, b)
  })
}

function withDateSortKeys(item) {
  if (!item || typeof item !== 'object') return item
  const extra = {}
  for (const field of DATE_FIELD_NAMES) {
    if (!(field in item)) continue
    const at = displayedDateTime(item[field], creationKey(item).created)
    extra[`${field}__at`] = at?.getTime() ?? null
  }
  return Object.keys(extra).length ? { ...item, ...extra } : item
}

function isDateLike(value) {
  if (value instanceof Date) return !Number.isNaN(value.getTime())
  if (typeof value !== 'string') return false
  return /^\d{4}-\d{2}-\d{2}(?:$|[T\s])/.test(value.trim())
}

/** Date + displayed hour (value time, else createdAt). Follows the chosen sort order. */
function compareDateField(a, b, field) {
  const ad = displayedDateTime(a?.[field], creationKey(a).created)
  const bd = displayedDateTime(b?.[field], creationKey(b).created)
  return compareValues(ad?.getTime() ?? null, bd?.getTime() ?? null)
}

function compareValues(av, bv) {
  if (av == null && bv == null) return 0
  if (av == null) return 1
  if (bv == null) return -1
  if (typeof av === 'number' && typeof bv === 'number') return av - bv
  if (typeof av === 'boolean' && typeof bv === 'boolean') return Number(av) - Number(bv)
  return String(av).localeCompare(String(bv), 'fr', { sensitivity: 'base', numeric: true })
}

/** Stable tie-break: creation order, then id. */
function compareCreationOrder(a, b) {
  const ac = creationKey(a)
  const bc = creationKey(b)
  const byCreated = compareValues(ac.created, bc.created)
  if (byCreated !== 0) return byCreated
  return compareValues(ac.id, bc.id)
}

function creationKey(item) {
  return {
    created: item?.createdAt ?? item?.created_at ?? item?.dateCreation ?? null,
    id: item?.id ?? null,
  }
}
