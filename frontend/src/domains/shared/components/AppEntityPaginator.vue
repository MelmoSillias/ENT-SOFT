<script setup>
import { computed } from 'vue'
import Paginator from 'primevue/paginator'

const props = defineProps({
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

const paginatorTemplate = computed(() =>
  props.position === 'top'
    ? 'PrevPageLink PageLinks NextPageLink'
    : 'PrevPageLink PageLinks NextPageLink CurrentPageReport',
)
</script>

<template>
  <Paginator
    class="app-entity-dataview__paginator"
    :class="`app-entity-dataview__paginator--${position}`"
    :rows="rows"
    :first="first"
    :total-records="totalRecords"
    :template="paginatorTemplate"
    current-page-report-template="Page {currentPage} / {totalPages}"
    @page="$emit('page', $event)"
  />
</template>
