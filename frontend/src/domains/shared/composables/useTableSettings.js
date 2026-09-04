import { computed, ref, watch } from 'vue'

const ROW_OPTIONS = [5, 10, 25, 50, 100]

/**
 * @param {string} storageKey
 * @param {{ key: string, label: string, defaultVisible?: boolean, sortable?: boolean }[]} columnsConfig
 * @param {{ defaultRows?: number, defaultSortField?: string|null, defaultSortOrder?: number, defaultShowIndex?: boolean }} [options]
 */
export function useTableSettings(storageKey, columnsConfig, options = {}) {
  const defaultRows = options.defaultRows ?? 10
  const defaultSortField = options.defaultSortField ?? null
  const defaultSortOrder = options.defaultSortOrder ?? 1
  const defaultShowIndex = options.defaultShowIndex ?? true

  function load() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) ?? 'null')
    } catch {
      return null
    }
  }

  function defaultVisibleKeys() {
    return columnsConfig
      .filter((c) => c.defaultVisible !== false)
      .map((c) => c.key)
  }

  const saved = load()
  const visibleColKeys = ref(
    Array.isArray(saved?.visibleColKeys) && saved.visibleColKeys.length
      ? saved.visibleColKeys
      : defaultVisibleKeys(),
  )
  const rows = ref(ROW_OPTIONS.includes(saved?.rows) ? saved.rows : defaultRows)
  const showIndex = ref(typeof saved?.showIndex === 'boolean' ? saved.showIndex : defaultShowIndex)
  const sortField = ref(saved?.sortField ?? defaultSortField)
  const sortOrder = ref(saved?.sortOrder === -1 || saved?.sortOrder === 1 ? saved.sortOrder : defaultSortOrder)

  watch(
    [visibleColKeys, rows, showIndex, sortField, sortOrder],
    () => {
      try {
        localStorage.setItem(
          storageKey,
          JSON.stringify({
            visibleColKeys: visibleColKeys.value,
            rows: rows.value,
            showIndex: showIndex.value,
            sortField: sortField.value,
            sortOrder: sortOrder.value,
          }),
        )
      } catch {
        /* ignore quota */
      }
    },
    { deep: true },
  )

  const columns = computed(() => columnsConfig)

  const sortOptions = computed(() =>
    columnsConfig
      .filter((c) => c.sortable !== false)
      .map((c) => ({ label: c.label, value: c.key })),
  )

  function isColVisible(key) {
    return visibleColKeys.value.includes(key)
  }

  function toggleCol(key, enabled) {
    if (enabled) {
      if (!visibleColKeys.value.includes(key)) {
        visibleColKeys.value = [...visibleColKeys.value, key]
      }
    } else {
      visibleColKeys.value = visibleColKeys.value.filter((k) => k !== key)
    }
  }

  return {
    ROW_OPTIONS,
    columns,
    visibleColKeys,
    rows,
    showIndex,
    sortField,
    sortOrder,
    sortOptions,
    isColVisible,
    toggleCol,
  }
}
