<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppEntityPaginator from '@/domains/shared/components/AppEntityPaginator.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { useClientPagination } from '@/domains/shared/composables/useClientPagination'
import { sortByField, tableSortField } from '@/domains/shared/utils/sortByField'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppDateTimeCell from '@/domains/shared/components/AppDateTimeCell.vue'
import InvoiceFormFields from '@/domains/finance/components/InvoiceFormFields.vue'
import TransactionAttachments from '@/domains/finance/components/TransactionAttachments.vue'
import ExportFormatMenu from '@/domains/impression/components/ExportFormatMenu.vue'
import { listInvoices, createInvoice, updateInvoice, deleteInvoice, payInvoice, resetInvoice } from '@/domains/finance/services/invoiceService'
import { listClients } from '@/domains/client/services/clientService'
import { listProjects } from '@/domains/project/services/projectService'
import { invoiceStatusLabel, invoiceStatusSeverity, formatDateFr } from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate, periodToApiParams, currentMonthRange, matchesDisplayedPeriod } from '@/domains/shared/utils/dateUtils'
import AppPeriodFilter from '@/domains/shared/components/AppPeriodFilter.vue'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { usePrintDocument } from '@/domains/impression/composables/usePrintDocument'
import AppTablePrintExportBar from '@/domains/impression/components/AppTablePrintExportBar.vue'
import { useTableExportPayload, moneyTotal } from '@/domains/impression/composables/useTableExportPayload'

defineProps({
  embedded: { type: Boolean, default: false },
})

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { printDocument, exportDocument } = usePrintDocument()
const { isAppMobile } = useAppMobileLayout()

const items = ref([])
const expandedMobileId = ref(null)
const clientOptions = ref([])
const projectOptions = ref([])
const clientMap = ref({})
const projectMap = ref({})
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const payDialog = ref(false)
const editingId = ref(null)
const payingItem = ref(null)
const actionMenu = ref()
const exportMenu = ref()
const printMenu = ref()
const menuModel = ref([])
const exportTarget = ref(null)
const printTarget = ref(null)
const expandedRows = ref([])
const rowContextMenu = ref()

const INVOICE_COLUMNS = [
  { key: 'number', label: 'N°', defaultVisible: true },
  { key: 'date', label: 'Date', defaultVisible: true },
  { key: 'client', label: 'Client', defaultVisible: true },
  { key: 'project', label: 'Projet', defaultVisible: true },
  { key: 'amount', label: 'Montant', defaultVisible: true, type: 'money', align: 'right' },
  { key: 'status', label: 'Statut', defaultVisible: true, align: 'center' },
]

const {
  ROW_OPTIONS,
  columns: tableColumns,
  visibleColKeys,
  rows: tableRows,
  showIndex,
  sortField,
  sortOrder,
  sortOptions,
  isColVisible,
  toggleCol,
} = useTableSettings('table_invoices_v2', INVOICE_COLUMNS, {
  defaultSortField: 'date',
  defaultSortOrder: -1,
})

const tableFirst = ref(0)

const canCreate = computed(() => hasPermission('finance.invoices.create'))

let lineUid = 0
function newLineKey() {
  return `il-${Date.now()}-${++lineUid}`
}

function emptyLine() {
  return { _key: newLineKey(), description: '', unit: 'Lot', quantity: 1, unitPrice: 0 }
}

function mapFormLines(lines) {
  return (lines?.length ? lines : [emptyLine()]).map((l) => ({
    _key: l.id || newLineKey(),
    description: l.description ?? '',
    unit: l.unit ?? 'Lot',
    quantity: l.quantity ?? 1,
    unitPrice: l.unitPrice ?? 0,
  }))
}

function emptyForm() {
  return { date: new Date(), clientId: null, projectId: null, projectLabel: '', status: 'draft', lines: [emptyLine()] }
}

const form = ref(emptyForm())
const payForm = ref({ date: new Date(), amount: 0, description: '' })

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!form.value.date) errs.date = 'Date requise.'
  if (!form.value.clientId) errs.clientId = 'Client requis.'
  const lines = (form.value.lines ?? []).filter((l) => String(l.description || '').trim())
  if (!lines.length) errs.lines = 'Ajoutez au moins une ligne.'
  return errs
})

async function loadRefs() {
  const [clients, projects] = await Promise.all([listClients(), listProjects()])
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  projectOptions.value = projects.map((p) => ({ label: `${p.code} — ${p.title}`, value: p.id }))
  clientMap.value = Object.fromEntries(clients.map((c) => [c.id, c.title]))
  projectMap.value = Object.fromEntries(projects.map((p) => [p.id, p.title]))
}

function invoiceProjectLabel(item) {
  const free = String(item?.projectLabel || '').trim()
  if (free) return free
  if (item?.projectId) return projectMap.value[item.projectId] || '—'
  return '—'
}

function remainingAmount(item) {
  return Math.max(0, Number(item?.amount || 0) - Number(item?.paidAmount || 0))
}

const filterPeriod = ref(currentMonthRange())

async function fetchItems() {
  items.value = await listInvoices(periodToApiParams(filterPeriod.value))
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

watch(filterPeriod, () => {
  reload()
})

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value.filter((item) => matchesDisplayedPeriod(item, filterPeriod.value, 'date'))
  if (q) {
    list = list.filter((item) =>
      [item.number, clientMap.value[item.clientId], invoiceProjectLabel(item)].filter(Boolean).join(' ').toLowerCase().includes(q),
    )
  }
  const enriched = list.map((item) => ({
    ...item,
    _client: clientMap.value[item.clientId] || '',
    _project: invoiceProjectLabel(item),
  }))
  const fieldMap = { client: '_client', project: '_project' }
  const field = fieldMap[sortField.value] || sortField.value
  return sortByField(enriched, field, sortOrder.value)
})

const {
  first: mobileFirst,
  pageItems: mobilePageItems,
  totalRecords: mobileTotal,
  onPage: onMobilePage,
  rankOf: mobileRankOf,
} = useClientPagination(filteredItems, tableRows)

const printExport = useTableExportPayload({
  tableType: 'invoices',
  title: 'Factures',
  columns: INVOICE_COLUMNS,
  visibleColKeys,
  items: filteredItems,
  searchTerm,
  getValue: (item, key) => {
    if (key === 'date') return formatDateFr(item.date) || '—'
    if (key === 'client') return clientMap.value[item.clientId] || '—'
    if (key === 'project') return invoiceProjectLabel(item)
    if (key === 'amount') {
      return `${formatMontant(item.amount, DEVISE_APP)} · payé ${formatMontant(item.paidAmount, DEVISE_APP)}`
    }
    if (key === 'status') return invoiceStatusLabel(item.status)
    return item[key] ?? ''
  },
  buildTotals: ({ items }) => [
    moneyTotal('Total montant', items, 'amount'),
    moneyTotal('Total payé', items, 'paidAmount'),
  ],
  filtersSummary: () => (searchTerm.value.trim() ? `Recherche : ${searchTerm.value.trim()}` : ''),
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
    clientId: item.clientId,
    projectId: item.projectId,
    projectLabel: item.projectLabel ?? '',
    status: item.status ?? 'draft',
    lines: mapFormLines(item.lines),
  }
  resetErrors()
  dialog.value = true
}

function openDuplicate(item) {
  editingId.value = null
  form.value = {
    date: new Date(),
    clientId: item.clientId,
    projectId: item.projectId,
    projectLabel: item.projectLabel ?? '',
    status: 'draft',
    lines: mapFormLines(item.lines),
  }
  resetErrors()
  dialog.value = true
}

function openPay(item) {
  payingItem.value = item
  const remaining = Math.max(0, Number(item.amount || 0) - Number(item.paidAmount || 0))
  payForm.value = { date: new Date(), amount: remaining, description: '' }
  payDialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('finance.transactions.create')) {
    menu.push({ label: 'Payer', icon: 'pi pi-wallet', command: () => openPay(item) })
  }
  if (hasPermission('finance.invoices.create')) {
    menu.push({ label: 'Dupliquer', icon: 'pi pi-copy', command: () => openDuplicate(item) })
  }
  if (hasPermission('finance.invoices.update')) {
    menu.push({
      label: item.hasPayments ? 'Réinitialiser' : 'Modifier',
      icon: item.hasPayments ? 'pi pi-refresh' : 'pi pi-pencil',
      command: () => (item.hasPayments ? askReset(item) : openEdit(item)),
    })
  }
  if (hasPermission('finance.invoices.delete') && !item.hasPayments && item.status === 'draft') {
    menu.push({ label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', command: () => askDelete(item) })
  }
  menu.push({
    label: 'Exporter',
    icon: 'pi pi-download',
    command: (event) => {
      exportTarget.value = item
      exportMenu.value?.toggle(event.originalEvent || event)
    },
  })
  menu.push({
    label: 'Imprimer',
    icon: 'pi pi-print',
    items: [
      { label: 'HTML', command: () => printDocument('invoice', item.id) },
      { label: 'PDF', command: () => exportDocument('invoice', item.id, 'pdf') },
    ],
  })
  return menu
}

function toggleMenu(event, item) {
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function onRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
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

function askReset(item) {
  confirm.require({
    header: 'Réinitialiser la facture',
    message: `Annuler les paiements de ${item.number} et revenir en brouillon ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Réinitialiser', severity: 'warn' },
    accept: () => runReset(item),
  })
}

const { run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteInvoice(item.id)
    toast.add({ severity: 'success', summary: 'Facture', detail: 'Supprimée.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Facture', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: resetting, run: runReset } = useAsyncAction(async (item) => {
  try {
    const updated = await resetInvoice(item.id)
    toast.add({ severity: 'success', summary: 'Facture', detail: 'Paiements annulés, facture en brouillon.' })
    await fetchItems()
    openEdit(updated)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Facture', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    date: toApiDate(form.value.date),
    clientId: form.value.clientId,
    projectId: form.value.projectId || null,
    projectLabel: String(form.value.projectLabel || '').trim() || null,
    status: form.value.status,
    lines: (form.value.lines ?? [])
      .filter((l) => String(l.description || '').trim())
      .map((l) => ({
        description: String(l.description).trim(),
        unit: String(l.unit || 'Lot').trim() || 'Lot',
        quantity: Number(l.quantity || 0),
        unitPrice: Number(l.unitPrice || 0),
      })),
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

const { pending: paying, run: savePay } = useAsyncAction(async () => {
  if (!payingItem.value) return
  if (!payForm.value.date || Number(payForm.value.amount) <= 0) {
    toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Date et montant requis.' })
    return
  }
  try {
    await payInvoice(payingItem.value.id, {
      date: toApiDate(payForm.value.date),
      amount: Number(payForm.value.amount),
      description: payForm.value.description || null,
    })
    payDialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Paiement', detail: 'Enregistré. Facture passée à facturé.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Paiement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

function onExportSelect(format) {
  if (!exportTarget.value) return
  exportDocument('invoice', exportTarget.value.id, format, { download: true })
}

const printFormatItems = computed(() => [
  { label: 'HTML', command: () => printTarget.value && printDocument('invoice', printTarget.value.id) },
  { label: 'PDF', command: () => printTarget.value && exportDocument('invoice', printTarget.value.id, 'pdf') },
])
</script>

<template>
  <section class="invoice-list">
    <AppTablePanelHeader
      title="Factures"
      :count-label="countLabel"
      create-label="Nouvelle facture"
      :show-create="canCreate"
      :hide-create-on-mobile="isAppMobile"
      :sticky="isAppMobile"
      :reloading="reloading"
      show-search
      v-model:search-term="searchTerm"
      search-placeholder="Rechercher…"
      @create="openCreate"
      @reload="reload"
    >
      <template #actions>
        <AppTableSettingsPopover
          v-model:visible-col-keys="visibleColKeys"
          v-model:rows="tableRows"
          v-model:show-index="showIndex"
          v-model:sort-field="sortField"
          v-model:sort-order="sortOrder"
          :columns="tableColumns"
          :row-options="ROW_OPTIONS"
          :sort-options="sortOptions"
          @toggle-col="toggleCol"
        >
          <template #filters>
            <p class="app-table-settings__title">Filtres</p>
            <AppPeriodFilter v-model="filterPeriod" />
          </template>
        </AppTableSettingsPopover>
        <AppTablePrintExportBar
          :table-type="printExport.tableType"
          :title="printExport.title"
          :columns="printExport.columns"
          :rows="printExport.rows"
          :totals="printExport.totals"
          :results="printExport.results"
          :filters-summary="printExport.filtersSummary"
          :search-term="printExport.searchTerm"
        />
      </template>
    </AppTablePanelHeader>
    <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
      <div v-if="isAppMobile" class="app-entity-dataview">
        <AppEntityPaginator
          v-if="mobileTotal > 0"
          position="top"
          :rows="tableRows"
          :first="mobileFirst"
          :total-records="mobileTotal"
          @page="onMobilePage"
        />
        <article
          v-for="(item, index) in mobilePageItems"
          :key="item.id"
          class="app-entity-card"
          v-bind="rowContextMenu?.rowBindings(item) ?? {}"
          @click="expandedMobileId = expandedMobileId === item.id ? null : item.id"
        >
          <div class="app-entity-card__row">
            <span v-if="showIndex" class="app-entity-card__index" aria-hidden="true">{{ mobileRankOf(index) }}</span>
            <div style="min-width: 0; flex: 1">
              <p class="app-entity-card__code">{{ item.number }}</p>
              <h3 class="app-entity-card__title">{{ clientMap[item.clientId] || 'Client' }}</h3>
              <p class="app-entity-card__subtitle">{{ formatDateFr(item.date) }} · {{ invoiceProjectLabel(item) }}</p>
            </div>
            <div @click.stop>
              <Button
                v-if="buildMenuItems(item).length"
                icon="pi pi-ellipsis-v"
                text
                rounded
                @click="toggleMenu($event, item)"
              />
            </div>
          </div>
          <div class="app-entity-card__meta-row">
            <Tag :value="invoiceStatusLabel(item.status)" :severity="invoiceStatusSeverity(item.status)" rounded />
            <span class="app-entity-card__meta">
              {{ formatMontant(item.amount, DEVISE_APP) }}
              · payé {{ formatMontant(item.paidAmount, DEVISE_APP) }}
            </span>
          </div>
          <div v-if="expandedMobileId === item.id" class="invoice-expansion" @click.stop>
            <dl class="invoice-details">
              <div>
                <dt>N°</dt>
                <dd>{{ item.number }}</dd>
              </div>
              <div>
                <dt>Date</dt>
                <dd>{{ formatDateFr(item.date) || '—' }}</dd>
              </div>
              <div>
                <dt>Client</dt>
                <dd>{{ clientMap[item.clientId] || '—' }}</dd>
              </div>
              <div>
                <dt>Projet</dt>
                <dd>{{ invoiceProjectLabel(item) }}</dd>
              </div>
              <div>
                <dt>Statut</dt>
                <dd>{{ invoiceStatusLabel(item.status) }}</dd>
              </div>
              <div>
                <dt>Montant</dt>
                <dd>{{ formatMontant(item.amount, DEVISE_APP) }}</dd>
              </div>
              <div>
                <dt>Payé</dt>
                <dd>{{ formatMontant(item.paidAmount, DEVISE_APP) }}</dd>
              </div>
              <div>
                <dt>Reste</dt>
                <dd>{{ formatMontant(remainingAmount(item), DEVISE_APP) }}</dd>
              </div>
            </dl>
            <div class="invoice-expansion__section">
              <p class="invoice-expansion__title">Lignes</p>
              <p v-if="!(item.lines ?? []).length" class="invoice-expansion__empty">Aucune ligne.</p>
              <div v-for="(line, idx) in item.lines" :key="line.id || idx" class="invoice-payments__row">
                <div>
                  <strong>{{ line.description }}</strong>
                  — {{ line.quantity }} {{ line.unit || 'Lot' }} × {{ formatMontant(line.unitPrice, DEVISE_APP) }}
                  = {{ formatMontant(line.amount ?? Number(line.quantity || 0) * Number(line.unitPrice || 0), DEVISE_APP) }}
                </div>
              </div>
            </div>
            <div class="invoice-expansion__section">
              <p class="invoice-expansion__title">Paiements</p>
              <p v-if="!(item.payments ?? []).length" class="invoice-expansion__empty">Aucun paiement.</p>
              <div v-for="payment in item.payments" :key="payment.id" class="invoice-payments__row">
                <div>
                  <strong>{{ formatDateFr(payment.date) }}</strong>
                  — {{ formatMontant(payment.amount, DEVISE_APP) }}
                  <span v-if="payment.description"> · {{ payment.description }}</span>
                </div>
                <TransactionAttachments :owner-id="payment.id" />
              </div>
            </div>
          </div>
        </article>
        <AppEntityPaginator
          v-if="mobileTotal > 0"
          position="bottom"
          :rows="tableRows"
          :first="mobileFirst"
          :total-records="mobileTotal"
          @page="onMobilePage"
        />
      </div>
      <DataTable
        v-else
        v-model:expandedRows="expandedRows"
        :value="filteredItems"
        paginator
        :rows="tableRows"
        striped-rows
        data-key="id"
        :sort-field="sortField === 'client' || sortField === 'project' ? undefined : tableSortField(sortField)"
        :sort-order="sortOrder"
        table-style="table-layout: fixed; width: 100%"
        @row-contextmenu="onRowContextMenu"
        v-model:first="tableFirst">
        <Column expander style="width: 2.5rem" />
        <Column v-if="showIndex" header="#" style="width: 2.75rem">
          <template #body="{ index }">{{ tableFirst + index + 1 }}</template>
        </Column>
        <Column v-if="isColVisible('number')" field="number" header="N°" sortable style="width: 5.5rem" />
        <Column v-if="isColVisible('date')" field="date" sort-field="date__at" header="Date" sortable style="width: 6.25rem">
          <template #body="{ data }">
            <AppDateTimeCell :value="data.date" :time-from="data.createdAt" />
          </template>
        </Column>
        <Column v-if="isColVisible('client')" header="Client" style="width: 8.5rem">
          <template #body="{ data }">
            <span class="invoice-cell-ellipsis">{{ clientMap[data.clientId] || '—' }}</span>
          </template>
        </Column>
        <Column v-if="isColVisible('project')" header="Projet">
          <template #body="{ data }">
            <span class="invoice-cell-ellipsis">{{ invoiceProjectLabel(data) }}</span>
          </template>
        </Column>
        <Column v-if="isColVisible('amount')" field="amount" header="Montant" sortable style="width: 7.75rem">
          <template #body="{ data }">
            <div class="invoice-amount-cell">
              <span>{{ formatMontant(data.amount, DEVISE_APP) }}</span>
              <small>Payé {{ formatMontant(data.paidAmount, DEVISE_APP) }}</small>
            </div>
          </template>
        </Column>
        <Column v-if="isColVisible('status')" field="status" header="Statut" sortable style="width: 6.25rem">
          <template #body="{ data }">
            <Tag :value="invoiceStatusLabel(data.status)" :severity="invoiceStatusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Actions" style="width: 4.25rem">
          <template #body="{ data }">
            <Button v-if="buildMenuItems(data).length" icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
          </template>
        </Column>
        <template #expansion="{ data }">
          <div class="invoice-expansion">
            <dl class="invoice-details">
              <div>
                <dt>N°</dt>
                <dd>{{ data.number }}</dd>
              </div>
              <div>
                <dt>N° séquentiel</dt>
                <dd>{{ data.numberSequential || '—' }}</dd>
              </div>
              <div>
                <dt>N° mensuel</dt>
                <dd>{{ data.numberMonthly || '—' }}</dd>
              </div>
              <div>
                <dt>Date</dt>
                <dd>{{ formatDateFr(data.date) || '—' }}</dd>
              </div>
              <div>
                <dt>Client</dt>
                <dd>{{ clientMap[data.clientId] || '—' }}</dd>
              </div>
              <div>
                <dt>Projet</dt>
                <dd>{{ invoiceProjectLabel(data) }}</dd>
              </div>
              <div>
                <dt>Statut</dt>
                <dd>{{ invoiceStatusLabel(data.status) }}</dd>
              </div>
              <div>
                <dt>Montant</dt>
                <dd>{{ formatMontant(data.amount, DEVISE_APP) }}</dd>
              </div>
              <div>
                <dt>Payé</dt>
                <dd>{{ formatMontant(data.paidAmount, DEVISE_APP) }}</dd>
              </div>
              <div>
                <dt>Reste</dt>
                <dd>{{ formatMontant(remainingAmount(data), DEVISE_APP) }}</dd>
              </div>
            </dl>
            <div class="invoice-expansion__section">
              <p class="invoice-expansion__title">Lignes</p>
              <p v-if="!(data.lines ?? []).length" class="invoice-expansion__empty">Aucune ligne.</p>
              <table v-else class="invoice-lines-table">
                <thead>
                  <tr>
                    <th>Libellé</th>
                    <th>Unité</th>
                    <th>Qté</th>
                    <th>P.U.</th>
                    <th>Montant</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in data.lines" :key="line.id || idx">
                    <td>{{ line.description }}</td>
                    <td>{{ line.unit || 'Lot' }}</td>
                    <td>{{ line.quantity }}</td>
                    <td>{{ formatMontant(line.unitPrice, DEVISE_APP) }}</td>
                    <td>{{ formatMontant(line.amount ?? Number(line.quantity || 0) * Number(line.unitPrice || 0), DEVISE_APP) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="invoice-expansion__section">
              <p class="invoice-expansion__title">Paiements</p>
              <p v-if="!(data.payments ?? []).length" class="invoice-expansion__empty">Aucun paiement.</p>
              <div v-for="payment in data.payments" :key="payment.id" class="invoice-payments__row">
                <div>
                  <strong>{{ formatDateFr(payment.date) }}</strong>
                  — {{ formatMontant(payment.amount, DEVISE_APP) }}
                  <span v-if="payment.description"> · {{ payment.description }}</span>
                </div>
                <TransactionAttachments :owner-id="payment.id" />
              </div>
            </div>
          </div>
        </template>
      </DataTable>
      <Menu ref="actionMenu" :model="menuModel" popup />
      <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
      <ExportFormatMenu ref="exportMenu" @select="onExportSelect" />
      <Menu ref="printMenu" :model="printFormatItems" popup />
    </AppTableState>

    <AppMobileFab
      v-if="isAppMobile && canCreate"
      aria-label="Nouvelle facture"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(840px, 96vw)">
      <InvoiceFormFields v-model="form" :errors="fieldErrors" :client-options="clientOptions" :project-options="projectOptions" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving || resetting" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>

    <Dialog v-model:visible="payDialog" header="Enregistrer un paiement" modal style="width: min(480px, 95vw)">
      <div class="pay-form">
        <div class="field">
          <label>Date</label>
          <DatePicker v-model="payForm.date" date-format="dd/mm/yy" show-icon fluid />
        </div>
        <div class="field">
          <label>Montant</label>
          <InputNumber v-model="payForm.amount" mode="currency" :currency="DEVISE_APP.code" locale="fr-FR" :min-fraction-digits="0" fluid />
        </div>
        <div class="field">
          <label>Libellé</label>
          <InputText v-model="payForm.description" fluid />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="paying" @click="payDialog = false" />
        <Button label="Payer" icon="pi pi-check" :loading="paying" @click="savePay" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.invoice-cell-ellipsis {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.invoice-amount-cell {
  display: grid;
  gap: 0.1rem;
  text-align: right;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}

.invoice-amount-cell small {
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.75rem;
}

.invoice-details {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr));
  gap: 0.65rem 1rem;
  margin: 0;
}

.invoice-details dt {
  margin: 0;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.invoice-details dd {
  margin: 0.15rem 0 0;
  font-weight: 600;
}

.invoice-expansion {
  padding: 0.75rem 0.5rem 1rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.invoice-expansion__title {
  margin: 0 0 0.5rem;
  font-weight: 600;
}

.invoice-expansion__empty {
  margin: 0;
  color: var(--layout-text-muted);
}

.invoice-lines-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.invoice-lines-table th,
.invoice-lines-table td {
  padding: 0.35rem 0.5rem;
  text-align: left;
  border-bottom: 1px solid var(--p-content-border-color, #e2e8f0);
}

.invoice-lines-table th:nth-child(n + 3),
.invoice-lines-table td:nth-child(n + 3) {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.invoice-payments__row {
  display: grid;
  gap: 0.5rem;
  margin-bottom: 0.85rem;
}

.pay-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
</style>
