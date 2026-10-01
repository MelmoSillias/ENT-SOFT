/**
 * Helpers to build table print/export payloads for the central impression module.
 */

/**
 * @param {Array<{ key: string, label: string, align?: string, type?: string, defaultVisible?: boolean }>} columns
 * @param {Record<string, boolean>|null|undefined} visibleColumns
 */
export function exportColumns(columns = [], visibleColumns = null) {
  return (columns || [])
    .filter((col) => {
      if (!col?.key || col.key === 'actions') return false
      if (visibleColumns && Object.prototype.hasOwnProperty.call(visibleColumns, col.key)) {
        return Boolean(visibleColumns[col.key])
      }
      return col.defaultVisible !== false
    })
    .map((col) => ({
      key: col.key,
      label: col.label || col.key,
      align: col.align || (['money', 'number', 'quantity'].includes(col.type) ? 'right' : 'left'),
      type: col.type || 'text',
    }))
}

/**
 * @param {Array<Record<string, any>>} items
 * @param {Array<{ key: string }>} columns
 * @param {(item: Record<string, any>, key: string) => any} [getValue]
 */
export function exportRows(items = [], columns = [], getValue = null) {
  return (items || []).map((item) => {
    const row = {}
    for (const col of columns) {
      const raw = typeof getValue === 'function' ? getValue(item, col.key) : item?.[col.key]
      row[col.key] = raw == null ? '' : raw
    }
    return row
  })
}

/**
 * @param {Array<Record<string, any>>} items
 * @param {string} field
 */
export function sumField(items = [], field) {
  return (items || []).reduce((acc, item) => {
    const n = Number(item?.[field])
    return acc + (Number.isFinite(n) ? n : 0)
  }, 0)
}

/**
 * @param {string} label
 * @param {string|number} value
 * @param {{ key?: string, align?: string }} [options]
 */
export function totalLine(label, value, options = {}) {
  return {
    label,
    value: value == null ? '' : String(value),
    align: options.align || 'right',
    key: options.key || undefined,
  }
}

/**
 * @param {string} label
 * @param {string|number} value
 */
export function resultLine(label, value) {
  return {
    label,
    value: value == null ? '' : String(value),
  }
}
