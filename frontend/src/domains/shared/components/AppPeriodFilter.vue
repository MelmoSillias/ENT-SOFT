<script setup>
import DatePicker from 'primevue/datepicker'

const model = defineModel({ type: Array, default: null })

defineProps({
  placeholder: { type: String, default: 'Période' },
})
</script>

<template>
  <DatePicker
    v-model="model"
    selection-mode="range"
    date-format="dd/mm/yy"
    show-icon
    icon-display="button"
    show-clear
    :placeholder="placeholder"
    fluid
    size="small"
    class="app-table-settings__mb"
  />
</template>
