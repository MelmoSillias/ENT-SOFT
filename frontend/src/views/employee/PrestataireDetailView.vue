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
import { toApiDate } from '@/domains/shared/utils/dateUtils'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppDetailInfoList from '@/domains/shared/components/AppDetailInfoList.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppFilterSelect from '@/domains/shared/components/AppFilterSelect.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
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
  { key: 'description', label: 'Description', defaultVisible: true },
  { key: 'site', label: 'Site', defaultVisible: true },
  { key: 'amount', label: 'Montant', defaultVisible: true },
  { key: 'paidAmount', label: 'Payé', defaultVisible: true },
  { key: 'workStatus', label: 'Statut', defaultVisible: true },
  { key: 'paymentStatus', label: 'Paiement', defaultVisible: true },
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
  defaultSortField: 'description',
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
  return { description: '', siteId: null, amount: 0, workStatus: 'pending' }
}
function emptyPayForm() {
  return { amount: 0, date: new Date(), description: '' }
}

const form = ref(emptyForm())
const payForm = ref(emptyPayForm())
const statusForm = ref({ workStatus: 'pending' })

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
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
        `<tr><td>${p.description}</td><td>${p.siteId ? siteMap.value[p.siteId] || '' : ''}</td><td>${p.amount}</td><td>${WORK_STATUS_LABEL[p.workStatus] || ''}</td><td>${PAYMENT_STATUS_LABEL[p.paymentStatus] || ''}</td></tr>`,
    )
    .join('')
  win.document.write(`<html><head><title>Prestations</title></head><body><h1>Prestations — ${prestataire.value?.name || ''}</h1><table border="1" cellpadding="6"><thead><tr><th>Description</th><th>Site</th><th>Montant</th><th>Statut</th><th>Paiement</th></tr></thead><tbody>${rows}</tbody></table></body></html>`)
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
          <div>
            <h1 class="detail-header__title">{{ prestataire.name || `${prestataire.prenom} ${prestataire.nom}` }}</h1>
            <Tag :value="prestataire.isEnabled ? 'Actif' : 'Inactif'" />
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
                :title-of="(item) => item.description"
                :subtitle-of="(item) => (item.siteId ? siteMap[item.siteId] || null : null)"
                :meta-of="(item) => `${formatMontant(item.amount, DEVISE_APP)} · ${formatMontant(item.paidAmount ?? 0, DEVISE_APP)} payé`"
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
.required { color: var(--p-red-500, #ef4444); }
.dashboard-page__state {
  padding: 1.5rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
