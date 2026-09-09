<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Menu from 'primevue/menu'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import { useConfirm } from 'primevue/useconfirm'
import {
  getPrestataire,
  listPrestations,
  createPrestation,
  updatePrestation,
  deletePrestation,
  payPrestation,
  payPrestationsBatch,
  changePrestationStatus,
  duplicatePrestation,
  resetPrestationPayments,
} from '@/domains/employee/services/prestataireService'
import { listSites } from '@/domains/site/services/siteService'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { toApiDate, parseApiDate } from '@/domains/shared/utils/dateUtils'
import { formatDateFr } from '@/domains/shared/utils/entLabels'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppDetailInfoList from '@/domains/shared/components/AppDetailInfoList.vue'
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppFilterSelect from '@/domains/shared/components/AppFilterSelect.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import { personDisplayName } from '@/domains/shared/utils/personDisplay'
import ExportFormatMenu from '@/domains/impression/components/ExportFormatMenu.vue'
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

const route = useRoute()
const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const prestataire = ref(null)
const prestations = ref([])
const siteOptions = ref([])
const siteMap = ref({})
const loading = ref(true)
const error = ref(null)
const activeTab = ref(route.query.tab === '1' ? '1' : '0')
const dialog = ref(false)
const payDialog = ref(false)
const multiPayDialog = ref(false)
const statusDialog = ref(false)
const editingId = ref(null)
const currentItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const exportMenu = ref()
const rowContextMenu = ref()
const searchTerm = ref('')
const filterWorkStatus = ref(null)
const filterPaymentStatus = ref(null)

const PRESTATION_COLUMNS = [
  { key: 'date', label: 'Date', defaultVisible: true },
  { key: 'description', label: 'Description', defaultVisible: true },
  { key: 'site', label: 'Site', defaultVisible: true },
  { key: 'amount', label: 'Montant', defaultVisible: true },
  { key: 'paidAmount', label: 'Payé', defaultVisible: true },
  { key: 'workStatus', label: 'Statut', defaultVisible: true },
  { key: 'paymentStatus', label: 'Paiement', defaultVisible: true },
  { key: 'createdAt', label: 'Créé le', defaultVisible: false },
  { key: 'updatedAt', label: 'Modifié le', defaultVisible: false },
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
} = useTableSettings('table_prestataire_prestations', PRESTATION_COLUMNS, {
  defaultSortField: 'date',
  defaultSortOrder: -1,
})

const PAYMENT_STATUS_OPTIONS = [
  { label: 'Impayé', value: 'unpaid' },
  { label: 'Partiel', value: 'partial' },
  { label: 'Payé', value: 'paid' },
]

const WORK_STATUS_OPTIONS = [
  { label: 'En attente', value: 'pending' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminée', value: 'completed' },
]
const PAYMENT_STATUS_LABEL = { unpaid: 'Impayé', partial: 'Partiel', paid: 'Payé' }
const PAYMENT_STATUS_SEVERITY = { unpaid: 'danger', partial: 'warn', paid: 'success' }
const WORK_STATUS_LABEL = Object.fromEntries(WORK_STATUS_OPTIONS.map((o) => [o.value, o.label]))
const WORK_STATUS_SEVERITY = { pending: 'secondary', in_progress: 'info', completed: 'success' }

const filteredPrestations = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = prestations.value
  if (filterWorkStatus.value) {
    list = list.filter((p) => p.workStatus === filterWorkStatus.value)
  }
  if (filterPaymentStatus.value) {
    list = list.filter((p) => p.paymentStatus === filterPaymentStatus.value)
  }
  if (q) {
    list = list.filter((p) => {
      const site = p.siteId ? (siteMap.value[p.siteId] || '') : ''
      return (
        String(p.description || '').toLowerCase().includes(q) ||
        site.toLowerCase().includes(q) ||
        String(p.amount).includes(q)
      )
    })
  }
  return sortByField(list, sortField.value, sortOrder.value)
})

const prestataireTabItems = computed(() => [
  { value: '0', label: 'Informations', shortLabel: 'Infos' },
  { value: '1', label: `Prestations (${prestations.value.length})`, shortLabel: 'Presta.' },
])

const infoItems = computed(() => {
  if (!prestataire.value) return []
  return [
    { key: 'email', label: 'Email', icon: 'pi pi-envelope', value: prestataire.value.email },
    { key: 'phone', label: 'Téléphone', icon: 'pi pi-phone', value: prestataire.value.phone },
    { key: 'address', label: 'Adresse', icon: 'pi pi-map-marker', value: prestataire.value.address || null, full: true },
  ]
})

const prestationStatsItems = computed(() => {
  const list = prestations.value
  const total = list.length
  const byWork = { pending: 0, in_progress: 0, completed: 0 }
  const byPay = { unpaid: 0, partial: 0, paid: 0 }
  let amountTotal = 0
  let paidTotal = 0
  for (const p of list) {
    byWork[p.workStatus] = (byWork[p.workStatus] ?? 0) + 1
    byPay[p.paymentStatus] = (byPay[p.paymentStatus] ?? 0) + 1
    amountTotal += Number(p.amount) || 0
    paidTotal += Number(p.paidAmount) || 0
  }
  const reliquat = Math.max(0, amountTotal - paidTotal)
  return [
    { key: 'total', label: 'Total prestations', icon: 'pi pi-briefcase', value: total },
    { key: 'pending', label: 'En attente', icon: 'pi pi-clock', value: byWork.pending },
    { key: 'in_progress', label: 'En cours', icon: 'pi pi-play', value: byWork.in_progress },
    { key: 'completed', label: 'Terminées', icon: 'pi pi-check-circle', value: byWork.completed },
    { key: 'amount', label: 'Montant total', icon: 'pi pi-wallet', value: formatMontant(amountTotal, DEVISE_APP) },
    { key: 'paid', label: 'Montant payé', icon: 'pi pi-money-bill', value: formatMontant(paidTotal, DEVISE_APP) },
    { key: 'reliquat', label: 'Reliquat', icon: 'pi pi-exclamation-circle', value: formatMontant(reliquat, DEVISE_APP) },
    { key: 'unpaid', label: 'Impayées', icon: 'pi pi-times-circle', value: byPay.unpaid },
    { key: 'partial', label: 'Partiellement payées', icon: 'pi pi-minus-circle', value: byPay.partial },
    { key: 'paidCount', label: 'Payées', icon: 'pi pi-verified', value: byPay.paid },
  ]
})

function emptyForm() {
  return { date: new Date(), description: '', siteId: null, amount: 0, workStatus: 'pending' }
}
function emptyPayForm() {
  return { amount: 0, date: new Date(), description: '' }
}

const form = ref(emptyForm())
const payForm = ref(emptyPayForm())
const multiPayDate = ref(new Date())
const multiPayAmounts = ref({})
const statusForm = ref({ workStatus: 'pending' })

const unpaidPrestations = computed(() =>
  prestations.value.filter((p) => p.paymentStatus !== 'paid'),
)

const canMultiPay = computed(
  () => hasPermission('employee.prestations.pay') && unpaidPrestations.value.length > 0,
)

const multiPaySelected = computed(() =>
  unpaidPrestations.value
    .map((p) => {
      const amount = Number(multiPayAmounts.value[p.id] || 0)
      return amount > 0 ? { ...p, payAmount: amount } : null
    })
    .filter(Boolean),
)

const multiPayTotal = computed(() =>
  multiPaySelected.value.reduce((sum, p) => sum + Number(p.payAmount || 0), 0),
)

function remainingOf(item) {
  return Math.max(0, Number(item.amount || 0) - Number(item.paidAmount || 0))
}

function openMultiPay() {
  const amounts = {}
  for (const p of unpaidPrestations.value) {
    amounts[p.id] = null
  }
  multiPayAmounts.value = amounts
  multiPayDate.value = new Date()
  multiPayDialog.value = true
}

function fillMultiPayLine(item) {
  multiPayAmounts.value = {
    ...multiPayAmounts.value,
    [item.id]: remainingOf(item),
  }
}

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!form.value.date) errs.date = 'Date requise.'
  if (!hasRequiredText(form.value.description)) errs.description = requiredMessage('Description')
  if (form.value.amount == null || Number(form.value.amount) <= 0) errs.amount = 'Montant invalide.'
  return errs
})

const { errors: payErrors, validate: validatePay, resetErrors: resetPayErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (payForm.value.amount == null || Number(payForm.value.amount) <= 0) errs.amount = 'Montant invalide.'
  if (!payForm.value.date) errs.date = 'Date requise.'
  return errs
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const [p, list, sites] = await Promise.all([
      getPrestataire(route.params.id),
      listPrestations(route.params.id),
      listSites(),
    ])
    prestataire.value = p
    prestations.value = list
    siteOptions.value = sites.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.id }))
    siteMap.value = Object.fromEntries(sites.map((s) => [s.id, s.title]))
    if (route.query.create === '1') {
      activeTab.value = '1'
      openCreate()
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le prestataire.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.id, load)

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    date: parseApiDate(item.date) || new Date(),
    description: item.description ?? '',
    siteId: item.siteId ?? null,
    amount: item.amount ?? 0,
    workStatus: item.workStatus ?? 'pending',
  }
  resetErrors()
  dialog.value = true
}

function openPay(item) {
  currentItem.value = item
  const remaining = Math.max(0, Number(item.amount || 0) - Number(item.paidAmount || 0))
  payForm.value = { amount: remaining, date: new Date(), description: '' }
  resetPayErrors()
  payDialog.value = true
}

function openStatus(item) {
  currentItem.value = item
  statusForm.value = { workStatus: item.workStatus }
  statusDialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('employee.prestations.pay') && item.paymentStatus !== 'paid') {
    menu.push({ label: 'Payer', icon: 'pi pi-wallet', command: () => openPay(item) })
  }
  if (hasPermission('employee.prestataires.update')) {
    menu.push({ label: 'Changer statut', icon: 'pi pi-sync', command: () => openStatus(item) })
    menu.push({ label: 'Dupliquer', icon: 'pi pi-copy', command: () => runDuplicate(item) })
    if (item.hasPayments) {
      menu.push({ label: 'Réinitialiser paiements', icon: 'pi pi-refresh', command: () => askReset(item) })
    } else {
      menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
    }
  }
  if (hasPermission('employee.prestataires.delete') && !item.hasPayments) {
    menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  }
  return menu
}

function toggleMenu(event, item) {
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function onPrestationRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer la prestation',
    message: 'Supprimer cette prestation ?',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

function askReset(item) {
  confirm.require({
    header: 'Réinitialiser les paiements',
    message: 'Les transactions liées seront soft-supprimées et exclues des stats. Continuer ?',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Réinitialiser', severity: 'danger' },
    accept: () => runReset(item),
  })
}

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    date: toApiDate(form.value.date),
    description: form.value.description.trim(),
    siteId: form.value.siteId,
    amount: Number(form.value.amount),
    workStatus: form.value.workStatus,
  }
  try {
    if (editingId.value) await updatePrestation(editingId.value, payload)
    else await createPrestation(route.params.id, payload)
    dialog.value = false
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Prestation', detail: 'Enregistrée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Prestation', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: paying, run: savePay } = useAsyncAction(async () => {
  if (!validatePay()) return
  try {
    await payPrestation(currentItem.value.id, {
      amount: Number(payForm.value.amount),
      date: toApiDate(payForm.value.date),
      description: payForm.value.description || null,
    })
    payDialog.value = false
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Paiement', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Paiement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: multiPaying, run: saveMultiPay } = useAsyncAction(async () => {
  if (!multiPayDate.value) {
    toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Date requise.' })
    return
  }
  if (multiPaySelected.value.length === 0) {
    toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Indiquez au moins un montant.' })
    return
  }
  try {
    await payPrestationsBatch(route.params.id, {
      date: toApiDate(multiPayDate.value),
      allocations: multiPaySelected.value.map((p) => ({
        prestationId: p.id,
        amount: Number(p.payAmount),
      })),
    })
    multiPayDialog.value = false
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Paiement', detail: 'Paiement groupé enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Paiement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: changingStatus, run: saveStatus } = useAsyncAction(async () => {
  try {
    await changePrestationStatus(currentItem.value.id, statusForm.value.workStatus)
    statusDialog.value = false
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Statut', detail: 'Mis à jour.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Statut', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { run: runDuplicate } = useAsyncAction(async (item) => {
  try {
    await duplicatePrestation(item.id)
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Prestation', detail: 'Dupliquée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Prestation', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deletePrestation(item.id)
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Prestation', detail: 'Supprimée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Prestation', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { run: runReset } = useAsyncAction(async (item) => {
  try {
    await resetPrestationPayments(item.id)
    prestations.value = await listPrestations(route.params.id)
    toast.add({ severity: 'success', summary: 'Paiements', detail: 'Réinitialisés.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Paiements', detail: e.response?.data?.error || 'Erreur.' })
  }
})

async function exportTable(format) {
  if (format === 'pdf' || format === 'word') {
    printTable()
    return
  }
  const rows = prestations.value.map((p) => ({
    Date: formatDateFr(p.date),
    Description: p.description,
    Site: p.siteId ? (siteMap.value[p.siteId] || p.siteId) : '',
    Montant: p.amount,
    Payé: p.paidAmount ?? 0,
    Reliquat: p.remainingAmount ?? Math.max(0, p.amount - (p.paidAmount || 0)),
    Statut: WORK_STATUS_LABEL[p.workStatus] || p.workStatus,
    Paiement: PAYMENT_STATUS_LABEL[p.paymentStatus] || p.paymentStatus,
  }))

  if (format === 'csv') {
    const headers = Object.keys(rows[0] || { Description: '' })
    const csv = [headers.join(';'), ...rows.map((r) => headers.map((h) => `"${String(r[h] ?? '').replace(/"/g, '""')}"`).join(';'))].join('\n')
    saveAs(new Blob([csv], { type: 'text/csv;charset=utf-8' }), `prestations-${new Date().toISOString().slice(0, 10)}.csv`)
    return
  }

  const wb = new ExcelJS.Workbook()
  const ws = wb.addWorksheet('Prestations')
  if (rows.length) {
    ws.columns = Object.keys(rows[0]).map((k) => ({ header: k, key: k, width: 18 }))
    rows.forEach((r) => ws.addRow(r))
  }
  const buffer = await wb.xlsx.writeBuffer()
  saveAs(new Blob([buffer]), `prestations-${new Date().toISOString().slice(0, 10)}.xlsx`)
}

function printTable() {
  const win = window.open('', '_blank')
  if (!win) return
  const rows = prestations.value
    .map(
      (p) =>
        `<tr><td>${formatDateFr(p.date)}</td><td>${p.description}</td><td>${p.siteId ? siteMap.value[p.siteId] || '' : ''}</td><td>${p.amount}</td><td>${WORK_STATUS_LABEL[p.workStatus] || ''}</td><td>${PAYMENT_STATUS_LABEL[p.paymentStatus] || ''}</td></tr>`,
    )
    .join('')
  win.document.write(`<html><head><title>Prestations</title></head><body><h1>Prestations — ${prestataire.value?.name || ''}</h1><table border="1" cellpadding="6"><thead><tr><th>Date</th><th>Description</th><th>Site</th><th>Montant</th><th>Statut</th><th>Paiement</th></tr></thead><tbody>${rows}</tbody></table></body></html>`)
  win.document.close()
  win.print()
}

const canCreate = computed(() => hasPermission('employee.prestataires.update'))
</script>

<template>
  <section class="dashboard-page">
    <div v-if="loading" class="dashboard-page__state">Chargement…</div>
    <div v-else-if="error" class="dashboard-page__state">{{ error }}</div>

    <Card v-else-if="prestataire" class="dashboard-panel">
      <template #title>
        <div class="detail-header">
          <div class="detail-header__identity">
            <AppPersonAvatar
              :name="personDisplayName(prestataire)"
              :photo-url="prestataire.photoUrl"
              size="large"
            />
            <div>
              <h1 class="detail-header__title">{{ personDisplayName(prestataire) }}</h1>
              <Tag :value="prestataire.isEnabled ? 'Actif' : 'Inactif'" />
            </div>
          </div>
          <div class="detail-header__actions">
            <Button label="Retour" icon="pi pi-arrow-left" text @click="router.push({ name: 'employees' })" />
          </div>
        </div>
      </template>
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="activeTab"
          :items="prestataireTabItems"
        />
        <Tabs v-model:value="activeTab">
          <TabList v-if="!isAppMobile">
            <Tab value="0">Informations</Tab>
            <Tab value="1">Prestations ({{ prestations.length }})</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <AppDetailInfoList :items="infoItems" />
              <h2 class="detail-section-title">Statistiques prestations</h2>
              <AppDetailInfoList :items="prestationStatsItems" />
            </TabPanel>
            <TabPanel value="1">
              <AppTablePanelHeader
                title="Prestations"
                :count-label="`${filteredPrestations.length}`"
                create-label="Ajouter prestation"
                :show-create="canCreate && !isAppMobile"
                :hide-create-on-mobile="true"
                :show-search="true"
                :search-term="searchTerm"
                search-placeholder="Rechercher une prestation…"
                :sticky="isAppMobile"
                @create="openCreate"
                @reload="load"
                @update:search-term="searchTerm = $event"
              >
                <template #actions>
                  <Button
                    v-if="canMultiPay"
                    label="Payer plusieurs"
                    icon="pi pi-wallet"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="openMultiPay"
                  />
                  <div v-if="!isAppMobile" class="prestations-toolbar__export">
                    <Button icon="pi pi-print" text rounded v-tooltip.top="'Imprimer'" @click="printTable" />
                    <Button icon="pi pi-download" text rounded v-tooltip.top="'Exporter'" @click="(e) => exportMenu?.toggle(e)" />
                    <ExportFormatMenu ref="exportMenu" @select="exportTable" />
                  </div>
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
                      <AppFilterSelect
                        v-model="filterWorkStatus"
                        :options="WORK_STATUS_OPTIONS"
                        option-label="label"
                        option-value="value"
                        placeholder="Statut travail"
                        show-clear
                        fluid
                        size="small"
                        class="app-table-settings__mb"
                      />
                      <AppFilterSelect
                        v-model="filterPaymentStatus"
                        :options="PAYMENT_STATUS_OPTIONS"
                        option-label="label"
                        option-value="value"
                        placeholder="Statut paiement"
                        show-clear
                        fluid
                        size="small"
                      />
                    </template>
                  </AppTableSettingsPopover>
                </template>
              </AppTablePanelHeader>

              <AppEntityDataView
                v-if="isAppMobile && filteredPrestations.length"
                :items="filteredPrestations"
                :rows="tableRows"
                :show-index="showIndex"
                :title-of="(item) => item.description"
                :subtitle-of="(item) => (item.siteId ? siteMap[item.siteId] || null : null)"
                :meta-of="(item) => `${formatDateFr(item.date)} · ${formatMontant(item.amount, DEVISE_APP)} · ${formatMontant(item.paidAmount ?? 0, DEVISE_APP)} payé`"
                :status-of="(item) => ({ value: PAYMENT_STATUS_LABEL[item.paymentStatus] || item.paymentStatus, severity: PAYMENT_STATUS_SEVERITY[item.paymentStatus] })"
                :actions-of="buildMenuItems"
                :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
              >
                <template #footer="{ item }">
                  <Tag
                    :value="WORK_STATUS_LABEL[item.workStatus] || item.workStatus"
                    :severity="WORK_STATUS_SEVERITY[item.workStatus]"
                    rounded
                  />
                </template>
              </AppEntityDataView>
              <DataTable
                v-else-if="!isAppMobile && filteredPrestations.length"
                :value="filteredPrestations"
                paginator
                :rows="tableRows"
                striped-rows
                :sort-field="sortField || undefined"
                :sort-order="sortOrder"
                @row-contextmenu="onPrestationRowContextMenu"
              >
                <Column v-if="showIndex" header="#" style="width: 3.5rem">
                  <template #body="{ index }">{{ index + 1 }}</template>
                </Column>
                <Column v-if="isColVisible('date')" field="date" header="Date" sortable>
                  <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
                </Column>
                <Column v-if="isColVisible('description')" field="description" header="Description" sortable />
                <Column v-if="isColVisible('site')" header="Site">
                  <template #body="{ data }">{{ data.siteId ? (siteMap[data.siteId] || '—') : '—' }}</template>
                </Column>
                <Column v-if="isColVisible('amount')" header="Montant" field="amount" sortable>
                  <template #body="{ data }">{{ formatMontant(data.amount, DEVISE_APP) }}</template>
                </Column>
                <Column v-if="isColVisible('paidAmount')" header="Payé" field="paidAmount" sortable>
                  <template #body="{ data }">{{ formatMontant(data.paidAmount ?? 0, DEVISE_APP) }}</template>
                </Column>
                <Column v-if="isColVisible('workStatus')" header="Statut" field="workStatus" sortable>
                  <template #body="{ data }">
                    <Tag :value="WORK_STATUS_LABEL[data.workStatus] || data.workStatus" :severity="WORK_STATUS_SEVERITY[data.workStatus]" />
                  </template>
                </Column>
                <Column v-if="isColVisible('paymentStatus')" header="Paiement" field="paymentStatus" sortable>
                  <template #body="{ data }">
                    <Tag :value="PAYMENT_STATUS_LABEL[data.paymentStatus] || data.paymentStatus" :severity="PAYMENT_STATUS_SEVERITY[data.paymentStatus]" />
                  </template>
                </Column>
                <Column v-if="isColVisible('createdAt')" field="createdAt" header="Créé le" sortable>
                  <template #body="{ data }">{{ formatDateFr(data.createdAt) }}</template>
                </Column>
                <Column v-if="isColVisible('updatedAt')" field="updatedAt" header="Modifié le" sortable>
                  <template #body="{ data }">{{ formatDateFr(data.updatedAt) }}</template>
                </Column>
                <Column header="Actions" style="width: 5rem">
                  <template #body="{ data }">
                    <Button icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
                  </template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">
                {{ prestations.length ? 'Aucune prestation ne correspond aux filtres.' : 'Aucune prestation.' }}
              </p>
              <Menu v-if="!isAppMobile" ref="actionMenu" :model="menuModel" popup />
              <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>

    <AppMobileFab
      v-if="isAppMobile && canCreate && prestataire && activeTab === '1'"
      aria-label="Ajouter prestation"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="editingId ? 'Modifier prestation' : 'Nouvelle prestation'" modal style="width: min(520px, 95vw)">
      <div class="field">
        <label>Date <span class="required">*</span></label>
        <DatePicker v-model="form.date" date-format="dd/mm/yy" show-icon :invalid="Boolean(fieldErrors.date)" fluid />
        <AppFieldError :message="fieldErrors.date" />
      </div>
      <div class="field">
        <label>Description <span class="required">*</span></label>
        <Textarea v-model="form.description" rows="3" :invalid="Boolean(fieldErrors.description)" fluid />
        <AppFieldError :message="fieldErrors.description" />
      </div>
      <div class="field">
        <label>Site</label>
        <Select v-model="form.siteId" :options="siteOptions" option-label="label" option-value="value" show-clear filter fluid />
      </div>
      <div class="field">
        <label>Montant <span class="required">*</span></label>
        <InputNumber v-model="form.amount" mode="currency" :currency="DEVISE_APP.code" locale="fr-FR" :min-fraction-digits="0" fluid />
        <AppFieldError :message="fieldErrors.amount" />
      </div>
      <div class="field">
        <label>Statut</label>
        <Select v-model="form.workStatus" :options="WORK_STATUS_OPTIONS" option-label="label" option-value="value" fluid />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="dialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>

    <Dialog v-model:visible="payDialog" header="Payer la prestation" modal style="width: min(420px, 95vw)">
      <div class="field">
        <label>Date</label>
        <DatePicker v-model="payForm.date" date-format="dd/mm/yy" show-icon fluid />
        <AppFieldError :message="payErrors.date" />
      </div>
      <div class="field">
        <label>Montant</label>
        <InputNumber v-model="payForm.amount" mode="currency" :currency="DEVISE_APP.code" locale="fr-FR" :min-fraction-digits="0" fluid />
        <AppFieldError :message="payErrors.amount" />
      </div>
      <div class="field">
        <label>Description</label>
        <Textarea v-model="payForm.description" rows="2" fluid />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="payDialog = false" />
        <Button label="Payer" icon="pi pi-check" :loading="paying" @click="savePay" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="multiPayDialog"
      header="Payer plusieurs prestations"
      modal
      style="width: min(720px, 96vw)"
      content-class="multi-pay-dialog"
    >
      <div class="field">
        <label>Date du paiement</label>
        <DatePicker v-model="multiPayDate" date-format="dd/mm/yy" show-icon fluid />
      </div>

      <div v-if="multiPaySelected.length" class="multi-pay-tags">
        <span class="multi-pay-tags__label">Incluses dans le paiement</span>
        <div class="multi-pay-tags__list">
          <Tag
            v-for="item in multiPaySelected"
            :key="item.id"
            :value="`${item.description} · ${formatMontant(item.payAmount, DEVISE_APP)}`"
            severity="info"
            rounded
          />
        </div>
      </div>

      <div class="multi-pay-total">
        <span>Total payé</span>
        <strong>{{ formatMontant(multiPayTotal, DEVISE_APP) }}</strong>
      </div>

      <div class="multi-pay-list">
        <div v-for="item in unpaidPrestations" :key="item.id" class="multi-pay-row">
          <div class="multi-pay-row__info">
            <p class="multi-pay-row__title">{{ item.description }}</p>
            <p class="multi-pay-row__meta">
              Reliquat {{ formatMontant(remainingOf(item), DEVISE_APP) }}
              · {{ PAYMENT_STATUS_LABEL[item.paymentStatus] || item.paymentStatus }}
            </p>
          </div>
          <div class="multi-pay-row__amount">
            <InputNumber
              v-model="multiPayAmounts[item.id]"
              mode="currency"
              :currency="DEVISE_APP.code"
              locale="fr-FR"
              :min="0"
              :min-fraction-digits="0"
              fluid
            />
            <Button
              icon="pi pi-wallet"
              text
              rounded
              v-tooltip.top="'Remplir le reliquat'"
              @click="fillMultiPayLine(item)"
            />
          </div>
        </div>
        <p v-if="!unpaidPrestations.length" class="dashboard-page__state">Aucune prestation impayée.</p>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="multiPayDialog = false" />
        <Button
          label="Valider le paiement"
          icon="pi pi-check"
          :loading="multiPaying"
          :disabled="multiPayTotal <= 0"
          @click="saveMultiPay"
        />
      </template>
    </Dialog>

    <Dialog v-model:visible="statusDialog" header="Changer le statut" modal style="width: min(380px, 95vw)">
      <div class="field">
        <label>Statut</label>
        <Select v-model="statusForm.workStatus" :options="WORK_STATUS_OPTIONS" option-label="label" option-value="value" fluid />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="statusDialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="changingStatus" @click="saveStatus" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
.detail-header__identity {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-width: 0;
}
.detail-header__title {
  margin: 0 0 0.35rem;
  font-size: 1.25rem;
}
.detail-header__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.detail-section-title {
  margin: 1.25rem 0 0.65rem;
  font-size: 0.95rem;
  font-weight: 650;
  color: var(--layout-text-color);
}
.prestations-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
  gap: 0.5rem;
}
.prestations-toolbar__export {
  display: inline-flex;
  gap: 0.15rem;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}
.multi-pay-tags {
  margin-bottom: 0.85rem;
}
.multi-pay-tags__label {
  display: block;
  margin-bottom: 0.4rem;
  font-size: 0.85rem;
  font-weight: 600;
}
.multi-pay-tags__list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.multi-pay-total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
  border-radius: 0.5rem;
  background: color-mix(in srgb, var(--p-primary-color, #3b82f6) 12%, transparent);
  font-size: 1rem;
}
.multi-pay-total strong {
  font-size: 1.25rem;
}
.multi-pay-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: min(50vh, 420px);
  overflow: auto;
}
.multi-pay-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}
.multi-pay-row__info {
  min-width: 0;
  flex: 1;
}
.multi-pay-row__title {
  margin: 0;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.multi-pay-row__meta {
  margin: 0.2rem 0 0;
  font-size: 0.8rem;
  color: var(--layout-text-muted);
}
.multi-pay-row__amount {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  width: min(220px, 45%);
  flex-shrink: 0;
}
.required { color: var(--p-red-500, #ef4444); }
.dashboard-page__state {
  padding: 1.5rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
