<script setup>
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import ExportFormatMenu from '@/domains/impression/components/ExportFormatMenu.vue'
import { useTableExport, useTablePrint } from '@/domains/impression/composables/usePrintDocument'
import { useAuthStore } from '@/domains/auth/stores/auth'
import AppDataImportButton from '@/domains/shared/components/AppDataImportButton.vue'
import { getImportDefinition, getTableImportTypes } from '@/domains/shared/utils/dataImport'

const props = defineProps({
  tableType: { type: String, required: true },
  title: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  filtersSummary: { type: String, default: '' },
  columns: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  totals: { type: Array, default: () => [] },
  results: { type: Array, default: () => [] },
  searchTerm: { type: String, default: '' },
  permissionPrint: { type: String, default: 'impression.documents.print' },
  permissionExport: { type: String, default: 'impression.tables.export' },
})

const emit = defineEmits(['imported'])

const exportMenu = ref()
const { printTable } = useTablePrint()
const { exportTable } = useTableExport()
const authStore = useAuthStore()

const canPrint = computed(() => authStore.hasPermission(props.permissionPrint))
const canExport = computed(() => authStore.hasPermission(props.permissionExport))
const importTypes = computed(() => getTableImportTypes(props.tableType).filter((type) =>
  authStore.hasPermission(getImportDefinition(type).permission),
))

const payload = () => ({
  title: props.title,
  filters: props.filters,
  filters_summary: props.filtersSummary,
  columns: props.columns,
  rows: props.rows,
  totals: props.totals,
  results: props.results,
  search: props.searchTerm,
})

const onPrint = () => {
  if (!canPrint.value) return
  printTable(props.tableType, payload())
}

const onExportSelect = (format) => {
  if (!canExport.value) return
  exportTable(props.tableType, format, payload())
}

const openExportMenu = (event) => {
  exportMenu.value?.toggle(event)
}
</script>

<template>
  <div v-if="canPrint || canExport || importTypes.length" class="app-table-print-export-bar">
    <AppDataImportButton
      v-for="type in importTypes"
      :key="type"
      :type="type"
      @imported="emit('imported', $event)"
    />
    <Button
      v-if="canPrint"
      icon="pi pi-print"
      text
      rounded
      size="small"
      severity="secondary"
      aria-label="Imprimer"
      v-tooltip.top="'Imprimer'"
      @click="onPrint"
    />
    <Button
      v-if="canExport"
      icon="pi pi-download"
      text
      rounded
      size="small"
      severity="secondary"
      aria-label="Exporter"
      v-tooltip.top="'Exporter'"
      @click="openExportMenu"
    />
    <ExportFormatMenu ref="exportMenu" @select="onExportSelect" />
  </div>
</template>

<style scoped>
.app-table-print-export-bar {
  display: inline-flex;
  align-items: center;
  gap: 0.125rem;
  flex-shrink: 0;
}
</style>
