<script setup>
import { computed, onMounted, ref } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import TransactionFormFields from '@/domains/finance/components/TransactionFormFields.vue'
import TransactionAttachments from '@/domains/finance/components/TransactionAttachments.vue'
import {
  listFinancialTransactions,
  createFinancialTransaction,
  updateFinancialTransaction,
  deleteFinancialTransaction,
} from '@/domains/finance/services/financialTransactionService'
import { listClients } from '@/domains/client/services/clientService'
import { listProjects } from '@/domains/project/services/projectService'
import { listSites } from '@/domains/site/services/siteService'
import {
  formatDateFr,
  transactionCategoryLabel,
  transactionStatusLabel,
  transactionStatusSeverity,
  transactionTypeLabel,
} from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate } from '@/domains/shared/utils/dateUtils'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'

const props = defineProps({
  expenseOnly: { type: Boolean, default: false },
  title: { type: String, default: 'Transactions' },
  createLabel: { type: String, default: 'Nouvelle transaction' },
})

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const items = ref([])
const clientOptions = ref([])
const projectOptions = ref([])
const siteOptions = ref([])
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const expandedRows = ref([])

const canCreate = computed(() => hasPermission('finance.transactions.create'))

function emptyForm() {
  return {
    date: new Date(),
    amount: 0,
    type: props.expenseOnly ? 'expense' : 'expense',
    category: props.expenseOnly ? 'OtherExpense' : 'OtherExpense',
    status: 'completed',
    fromParty: '',
    toParty: '',
    description: '',
    clientId: null,
    projectId: null,
    siteId: null,
  }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!form.value.date) errs.date = 'Date requise.'
  if (form.value.amount == null || Number(form.value.amount) <= 0) errs.amount = 'Montant invalide.'
  if (!hasRequiredText(form.value.fromParty)) errs.fromParty = requiredMessage('Émetteur')
  if (!hasRequiredText(form.value.toParty)) errs.toParty = requiredMessage('Destinataire')
  return errs
})

async function loadRefs() {
  const [clients, projects, sites] = await Promise.all([listClients(), listProjects(), listSites()])
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  projectOptions.value = projects.map((p) => ({ label: `${p.code} — ${p.title}`, value: p.id }))
  siteOptions.value = sites.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.id }))
}

async function fetchItems() {
  const all = await listFinancialTransactions()
  items.value = props.expenseOnly ? all.filter((t) => t.type === 'expense') : all
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadRefs()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les transactions.'
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
    [item.fromParty, item.toParty, item.description, item.category].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
})

const dialogTitle = computed(() => (editingId.value ? 'Modifier' : props.createLabel))

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
    type: item.type,
    category: item.category,
    status: item.status,
    fromParty: item.fromParty ?? '',
    toParty: item.toParty ?? '',
    description: item.description ?? '',
    clientId: item.clientId,
    projectId: item.projectId,
    siteId: item.siteId,
  }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('finance.transactions.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('finance.transactions.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer',
    message: 'Supprimer cette transaction ?',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteFinancialTransaction(item.id)
    toast.add({ severity: 'success', summary: 'Transaction', detail: 'Supprimée.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Transaction', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    date: toApiDate(form.value.date),
    amount: form.value.amount,
    type: props.expenseOnly ? 'expense' : form.value.type,
    category: form.value.category,
    status: form.value.status,
    fromParty: form.value.fromParty.trim(),
    toParty: form.value.toParty.trim(),
    description: form.value.description || null,
    clientId: form.value.clientId || null,
    projectId: form.value.projectId || null,
    siteId: form.value.siteId || null,
  }
  try {
    if (editingId.value) await updateFinancialTransaction(editingId.value, payload)
    else await createFinancialTransaction(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Transaction', detail: 'Enregistrée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Transaction', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <AppTablePanelHeader
    :title="title"
    :count-label="`${filteredItems.length}`"
    :create-label="createLabel"
    :show-create="canCreate"
    :reloading="reloading"
    show-search
    v-model:search-term="searchTerm"
    search-placeholder="Rechercher…"
    @create="openCreate"
    @reload="reload"
  />
  <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
    <DataTable v-model:expandedRows="expandedRows" :value="filteredItems" paginator :rows="10" striped-rows data-key="id">
      <Column expander style="width: 3rem" />
      <Column header="Date">
        <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
      </Column>
      <Column header="Type">
        <template #body="{ data }">{{ transactionTypeLabel(data.type) }}</template>
      </Column>
      <Column header="Catégorie">
        <template #body="{ data }">{{ transactionCategoryLabel(data.category) }}</template>
      </Column>
      <Column header="Montant">
        <template #body="{ data }">{{ formatMontant(data.amount, DEVISE_APP) }}</template>
      </Column>
      <Column field="fromParty" header="Émetteur" />
      <Column field="toParty" header="Destinataire" />
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="transactionStatusLabel(data.status)" :severity="transactionStatusSeverity(data.status)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 5rem">
        <template #body="{ data }">
          <Button v-if="buildMenuItems(data).length" icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
        </template>
      </Column>
      <template #expansion="{ data }">
        <div class="tx-expansion">
          <p v-if="data.description">{{ data.description }}</p>
          <TransactionAttachments :owner-id="data.id" />
        </div>
      </template>
    </DataTable>
    <Menu ref="actionMenu" :model="menuModel" popup />
  </AppTableState>

  <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(720px, 96vw)">
    <TransactionFormFields
      v-model="form"
      :errors="fieldErrors"
      :client-options="clientOptions"
      :project-options="projectOptions"
      :site-options="siteOptions"
      :expense-only="expenseOnly"
    />
    <TransactionAttachments v-if="editingId" :owner-id="editingId" />
    <template #footer>
      <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
      <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
    </template>
  </Dialog>
</template>

<style scoped>
.tx-expansion {
  padding: 0.75rem 0.5rem 1rem 2.5rem;
}
</style>
