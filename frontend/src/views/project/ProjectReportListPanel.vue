<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Menu from 'primevue/menu'
import Tag from 'primevue/tag'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { formatDateFr } from '@/domains/shared/utils/entLabels'
import {
  deleteProjectReport,
  exportProjectReport,
  listProjectReports,
} from '@/domains/project/services/projectReportService'
import { useConfirm } from 'primevue/useconfirm'

const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const items = ref([])
const loading = ref(true)
const error = ref(null)
const searchTerm = ref('')
const menu = ref(null)
const menuItems = ref([])
const activeItem = ref(null)
const exporting = ref(false)

const canCreate = computed(() => hasPermission('project.reports.create'))
const canUpdate = computed(() => hasPermission('project.reports.update'))
const canDelete = computed(() => hasPermission('project.reports.delete'))
const canExport = computed(() => hasPermission('project.reports.export'))
const filteredItems = computed(() => {
  const needle = searchTerm.value.trim().toLocaleLowerCase('fr')
  if (!needle) return items.value
  return items.value.filter((item) => [
    item.code,
    item.description,
    item.status,
    ...(item.sites || []).flatMap((site) => [site.siteCode, site.siteTitle, site.projectTitle]),
  ].some((value) => String(value || '').toLocaleLowerCase('fr').includes(needle)))
})

function statusLabel(status) {
  return status === 'finalized' ? 'Finalisé' : 'Brouillon'
}

function statusSeverity(status) {
  return status === 'finalized' ? 'success' : 'secondary'
}

function periodLabel(item) {
  const start = formatDateFr(item.startDate)
  const end = formatDateFr(item.endDate)
  return item.endDate && item.endDate !== item.startDate ? `${start} – ${end}` : start
}

async function load() {
  loading.value = true
  error.value = null
  try {
    items.value = await listProjectReports()
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les rapports.'
  } finally {
    loading.value = false
  }
}

function open(item) {
  router.push({ name: 'project-report-edit', params: { id: item.id } })
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 30_000)
}

async function runExport(item, format, bundle = false) {
  exporting.value = true
  try {
    const blob = await exportProjectReport(item.id, format, { bundle })
    downloadBlob(blob, `${item.code || 'rapport'}.${bundle ? 'zip' : format === 'excel' ? 'xlsx' : format === 'word' ? 'docx' : format}`)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Export', detail: e.response?.data?.error || 'Export impossible.' })
  } finally {
    exporting.value = false
  }
}

function remove(item) {
  confirm.require({
    header: 'Supprimer le rapport',
    message: `Supprimer définitivement ${item.code || 'ce rapport'} ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: async () => {
      try {
        await deleteProjectReport(item.id)
        items.value = items.value.filter((entry) => entry.id !== item.id)
        toast.add({ severity: 'success', summary: 'Rapport', detail: 'Rapport supprimé.' })
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Rapport', detail: e.response?.data?.error || 'Suppression impossible.' })
      }
    },
  })
}

function toggleMenu(event, item) {
  activeItem.value = item
  menuItems.value = [
    { label: 'Ouvrir', icon: 'pi pi-eye', command: () => open(item) },
    { label: 'Modifier', icon: 'pi pi-pencil', visible: canUpdate.value, command: () => open(item) },
    { separator: true, visible: canExport.value },
    { label: 'PDF', icon: 'pi pi-file-pdf', visible: canExport.value, command: () => runExport(item, 'pdf') },
    { label: 'Excel', icon: 'pi pi-file-excel', visible: canExport.value, command: () => runExport(item, 'excel') },
    { label: 'Word', icon: 'pi pi-file-word', visible: canExport.value, command: () => runExport(item, 'word') },
    { label: 'Archive avec originaux', icon: 'pi pi-box', visible: canExport.value, command: () => runExport(item, 'pdf', true) },
    { separator: true, visible: canDelete.value },
    { label: 'Supprimer', icon: 'pi pi-trash', visible: canDelete.value, command: () => remove(item) },
  ]
  menu.value.toggle(event)
}

onMounted(load)
</script>

<template>
  <div class="report-list">
    <AppTablePanelHeader
      title="Rapports d’activité"
      :count-label="`${filteredItems.length} rapport${filteredItems.length > 1 ? 's' : ''}`"
      create-label="Nouveau rapport"
      :show-create="canCreate"
      show-search
      v-model:search-term="searchTerm"
      search-placeholder="Code, description, projet ou site…"
      @create="router.push({ name: 'project-report-create' })"
      @reload="load"
    />
    <AppTableState
      :loading="loading"
      :error="error"
      :is-empty="!loading && !error && !filteredItems.length"
      @retry="load"
    >
      <DataTable :value="filteredItems" paginator :rows="15" striped-rows @row-dblclick="open($event.data)">
        <Column field="code" header="Code" sortable />
        <Column header="Période">
          <template #body="{ data }">{{ periodLabel(data) }}</template>
        </Column>
        <Column field="description" header="Description" />
        <Column header="Sites" style="width: 6rem">
          <template #body="{ data }">{{ data.siteCount ?? data.lineCount ?? data.sites?.length ?? 0 }}</template>
        </Column>
        <Column field="status" header="État" style="width: 8rem">
          <template #body="{ data }">
            <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Actions" style="width: 5rem">
          <template #body="{ data }">
            <Button
              icon="pi pi-ellipsis-v"
              text
              rounded
              :loading="exporting && activeItem?.id === data.id"
              aria-label="Actions"
              @click="toggleMenu($event, data)"
            />
          </template>
        </Column>
      </DataTable>
    </AppTableState>
    <Menu ref="menu" :model="menuItems" popup />
  </div>
</template>

<style scoped>
.report-list {
  display: grid;
  gap: 1rem;
  min-width: 0;
}
</style>
