import { computed, ref, toValue, watch } from 'vue'

/**
 * Client-side list pagination (mobile card lists / sliced views).
 * @param {import('vue').MaybeRefOrGetter<unknown[]>} items
 * @param {import('vue').MaybeRefOrGetter<number|null|undefined>} rows
 */
export function useClientPagination(items, rows) {
  const first = ref(0)

  const pageSize = computed(() => {
    const value = Number(toValue(rows))
    return Number.isFinite(value) && value > 0 ? value : null
  })

  const totalRecords = computed(() => toValue(items)?.length ?? 0)

  const pageItems = computed(() => {
    const list = toValue(items) ?? []
    if (!pageSize.value) return list
    return list.slice(first.value, first.value + pageSize.value)
  })

  watch(
    [() => toValue(items)?.length, pageSize],
    () => {
      if (!pageSize.value) {
        first.value = 0
        return
      }
      if (first.value >= totalRecords.value) {
        const lastPageStart = Math.max(0, Math.floor(Math.max(totalRecords.value - 1, 0) / pageSize.value) * pageSize.value)
        first.value = lastPageStart
      }
    },
  )

  function onPage(event) {
    first.value = event.first ?? 0
  }

  function rankOf(indexOnPage) {
    return first.value + indexOnPage + 1
  }

  return {
    first,
    pageSize,
    pageItems,
    totalRecords,
    onPage,
    rankOf,
  }
}
