/**
 * Sort a list by field / order (1 asc, -1 desc).
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
    const av = a?.[sortField]
    const bv = b?.[sortField]
    if (av == null && bv == null) return 0
    if (av == null) return 1
    if (bv == null) return -1
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * order
    return String(av).localeCompare(String(bv), 'fr', { sensitivity: 'base', numeric: true }) * order
  })
}
