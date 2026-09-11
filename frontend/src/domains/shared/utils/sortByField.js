/**
 * Sort a list by field / order (1 asc, -1 desc).
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
  return [...items].sort((a, b) => {
    const primary = compareValues(a?.[sortField], b?.[sortField])
    if (primary !== 0) return primary * order
    return compareCreationOrder(a, b)
  })
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
