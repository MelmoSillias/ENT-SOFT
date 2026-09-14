<script setup>
import DatePicker from 'primevue/datepicker'

const model = defineModel({ type: Array, default: null })

defineProps({
  placeholder: { type: String, default: 'Période' },
})

function sameDay(a, b) {
  if (!a || !b) return false
  return a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate()
}

function withBoundTime(date, role) {
  const d = new Date(date)
  if (role === 'start') d.setHours(0, 0, 0, 0)
  else d.setHours(23, 59, 59, 999)
  return d
}

function sameInstant(a, b) {
  if (!a && !b) return true
  if (!a || !b) return false
  return a.getTime() === b.getTime()
}

function onUpdate(value) {
  if (!Array.isArray(value)) {
    model.value = value
    return
  }
  const prev = Array.isArray(model.value) ? model.value : []
  const next = [value[0] ?? null, value[1] ?? null]
  if (next[0] && !sameDay(next[0], prev[0])) next[0] = withBoundTime(next[0], 'start')
  if (next[1] && !sameDay(next[1], prev[1])) next[1] = withBoundTime(next[1], 'end')
  if (sameInstant(next[0], prev[0]) && sameInstant(next[1], prev[1])) return
  model.value = next
}
</script>

<template>
  <DatePicker
    :model-value="model"
    selection-mode="range"
    date-format="dd/mm/yy"
    show-time
    hour-format="24"
    :step-minute="1"
    :hide-on-range-selection="false"
    show-icon
    icon-display="button"
    show-clear
    :placeholder="placeholder"
    fluid
    size="small"
    class="app-period-filter app-table-settings__mb"
    @update:model-value="onUpdate"
  />
</template>

<style scoped>
.app-period-filter {
  min-width: 16.5rem;
}
</style>
