import { computed, reactive, unref } from 'vue'
import {
  exportColumns,
  exportRows,
  resultLine,
  sumField,
  totalLine,
} from '@/domains/impression/utils/buildTableExportRows'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'

/**
 * Build reactive print/export props for AppTablePrintExportBar.
 */
export function useTableExportPayload(options) {
  const columns = computed(() => exportColumns(unref(options.columns) || [], unref(options.visibleColKeys)))
  const items = computed(() => unref(options.items) || [])
  const rows = computed(() => exportRows(items.value, columns.value, options.getValue || null))

  const totals = computed(() => {
    if (typeof options.buildTotals === 'function') {
      return options.buildTotals({ items: items.value, columns: columns.value }) || []
    }
    return []
  })

  const results = computed(() => {
    if (typeof options.buildResults === 'function') {
      return options.buildResults({ items: items.value, columns: columns.value }) || []
    }
    return [resultLine('Nombre de lignes', items.value.length)]
  })

  const filtersSummary = computed(() => {
    const raw = options.filtersSummary
    if (typeof raw === 'function') return raw({ items: items.value }) || ''
    return unref(raw) || ''
  })

  return reactive({
    tableType: computed(() => unref(options.tableType)),
    title: computed(() => unref(options.title) || ''),
    columns,
    rows,
    totals,
    results,
    filtersSummary,
    searchTerm: computed(() => unref(options.searchTerm) || ''),
    filters: computed(() => unref(options.filters) || {}),
  })
}

export function moneyTotal(label, items, field, key = field) {
  const sum = sumField(items, field)
  return totalLine(label, formatMontant(sum, DEVISE_APP), { key, align: 'right' })
}

export function quantityTotal(label, items, field, key = field) {
  const sum = sumField(items, field)
  const formatted = Number.isInteger(sum) ? String(sum) : String(Math.round(sum * 100) / 100)
  return totalLine(label, formatted, { key, align: 'right' })
}

export { exportColumns, exportRows, resultLine, sumField, totalLine, formatMontant, DEVISE_APP }
