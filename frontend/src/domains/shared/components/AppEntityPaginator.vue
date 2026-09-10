<script setup>
import Paginator from 'primevue/paginator'

defineProps({
  rows: {
    type: Number,
    required: true,
  },
  first: {
    type: Number,
    required: true,
  },
  totalRecords: {
    type: Number,
    required: true,
  },
  position: {
    type: String,
    default: 'bottom',
    validator: (value) => ['top', 'bottom'].includes(value),
  },
})

defineEmits(['page'])
</script>

<template>
  <Paginator
    class="app-entity-dataview__paginator"
    :class="`app-entity-dataview__paginator--${position}`"
    :rows="rows"
    :first="first"
    :total-records="totalRecords"
    template="PrevPageLink PageLinks NextPageLink CurrentPageReport"
    current-page-report-template="{first}-{last} / {totalRecords}"
    @page="$emit('page', $event)"
  />
</template>
