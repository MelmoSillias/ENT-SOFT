<script setup>
import { computed, onMounted, ref } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import InvoiceFormFields from '@/domains/finance/components/InvoiceFormFields.vue'
import { listInvoices, createInvoice, updateInvoice, deleteInvoice } from '@/domains/finance/services/invoiceService'
import { listClients } from '@/domains/client/services/clientService'
import { listProjects } from '@/domains/project/services/projectService'
import { invoiceStatusLabel, invoiceStatusSeverity, formatDateFr } from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate } from '@/domains/shared/utils/dateUtils'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const items = ref([])
const clientOptions = ref([])
const projectOptions = ref([])
const clientMap = ref({})
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])

const canCreate = computed(() => hasPermission('finance.invoices.create'))

function emptyForm() {
  return { date: new Date(), amount: 0, clientId: null, projectId: null, status: 'draft' }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!form.value.date) errs.date = 'Date requise.'
  if (!form.value.clientId) errs.clientId = 'Client requis.'
  if (form.value.amount == null || Number(form.value.amount) <= 0) errs.amount = 'Montant invalide.'
  return errs
})

async function loadRefs() {
  const [clients, projects] = await Promise.all([listClients(), listProjects()])
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  projectOptions.value = projects.map((p) => ({ label: `${p.code} — ${p.title}`, value: p.id }))
  clientMap.value = Object.fromEntries(clients.map((c) => [c.id, c.title]))
}

async function fetchItems() {
  items.value = await listInvoices()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadRefs()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les factures.'
  } finally {
    loading.value = false
  }
}

async function reload() {
  reloading.value = true
  try {
    await fetchItems()
  } finally {
    reloading.value = false
  }
}

onMounted(load)

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  if (!q) return items.value
  return items.value.filter((item) =>
    [item.number, clientMap.value[item.clientId]].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
})

const countLabel = computed(() => `${filteredItems.value.length}`)
const dialogTitle = computed(() => (editingId.value ? 'Modifier facture' : 'Nouvelle facture'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    date: parseApiDate(item.date),
    amount: item.amount,
    clientId: item.clientId,
    projectId: item.projectId,
    status: item.status ?? 'draft',
  }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('finance.invoices.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('finance.invoices.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer la facture',
    message: `Supprimer la facture ${item.number} ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteInvoice(item.id)
    toast.add({ severity: 'success', summary: 'Facture', detail: 'Supprimée.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Facture', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    date: toApiDate(form.value.date),
    amount: form.value.amount,
    clientId: form.value.clientId,
    projectId: form.value.projectId || null,
    status: form.value.status,
  }
  try {
    if (editingId.value) await updateInvoice(editingId.value, payload)
    else await createInvoice(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Facture', detail: 'Enregistrée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Facture', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Factures"
          :count-label="countLabel"
          create-label="Nouvelle facture"
          :show-create="canCreate"
          :reloading="reloading"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher…"
          @create="openCreate"
          @reload="reload"
        />
      </template>
      <template #content>
        <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
          <DataTable :value="filteredItems" paginator :rows="10" striped-rows>
            <Column field="number" header="N°" />
            <Column header="Date">
              <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
            </Column>
            <Column header="Client">
              <template #body="{ data }">{{ clientMap[data.clientId] || '—' }}</template>
            </Column>
            <Column header="Montant">
              <template #body="{ data }">{{ formatMontant(data.amount, DEVISE_APP) }}</template>
            </Column>
            <Column header="Statut">
              <template #body="{ data }">
                <Tag :value="invoiceStatusLabel(data.status)" :severity="invoiceStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column header="Actions" style="width: 5rem">
              <template #body="{ data }">
                <Button v-if="buildMenuItems(data).length" icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
              </template>
            </Column>
          </DataTable>
          <Menu ref="actionMenu" :model="menuModel" popup />
        </AppTableState>
      </template>
    </Card>

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(640px, 95vw)">
      <InvoiceFormFields v-model="form" :errors="fieldErrors" :client-options="clientOptions" :project-options="projectOptions" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
