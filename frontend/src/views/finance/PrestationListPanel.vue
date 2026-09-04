<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import { useConfirm } from 'primevue/useconfirm'
import {
  listAllPrestations,
  listPrestataires,
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
import { sortByField } from '@/domains/shared/utils/sortByField'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppFilterSelect from '@/domains/shared/components/AppFilterSelect.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'

const toast = useAppToast()
const confirm = useConfirm()
const router = useRouter()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const items = ref([])
const prestataireOptions = ref([])
const siteOptions = ref([])
const siteMap = ref({})
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const searchTerm = ref('')
const filterWorkStatus = ref(null)
const filterPaymentStatus = ref(null)
const dialog = ref(false)
const payDialog = ref(false)
const statusDialog = ref(false)
const editingId = ref(null)
const currentItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const rowContextMenu = ref()

const WORK_STATUS_OPTIONS = [
  { label: 'En attente', value: 'pending' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminée', value: 'completed' },
]
const PAYMENT_STATUS_OPTIONS = [
  { label: 'Impayé', value: 'unpaid' },
  { label: 'Partiel', value: 'partial' },
  { label: 'Payé', value: 'paid' },
]
const PAYMENT_STATUS_LABEL = { unpaid: 'Impayé', partial: 'Partiel', paid: 'Payé' }
const PAYMENT_STATUS_SEVERITY = { unpaid: 'danger', partial: 'warn', paid: 'success' }
const WORK_STATUS_LABEL = Object.fromEntries(WORK_STATUS_OPTIONS.map((o) => [o.value, o.label]))
const WORK_STATUS_SEVERITY = { pending: 'secondary', in_progress: 'info', completed: 'success' }

const PRESTATION_COLUMNS = [
  { key: 'prestataire', label: 'Prestataire', defaultVisible: true },
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
} = useTableSettings('table_finance_prestations', PRESTATION_COLUMNS, {
  defaultSortField: 'prestataireName',
})

function emptyForm() {
  return { prestataireId: null, description: '', siteId: null, amount: 0, workStatus: 'pending' }
}
function emptyPayForm() {
  return { amount: 0, date: new Date(), description: '' }
}

const form = ref(emptyForm())
const payForm = ref(emptyPayForm())
const statusForm = ref({ workStatus: 'pending' })

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!form.value.prestataireId && !editingId.value) errs.prestataireId = requiredMessage('Prestataire')
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

const canCreate = computed(() => hasPermission('employee.prestataires.update') || hasPermission('employee.prestataires.create'))

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (filterWorkStatus.value) list = list.filter((p) => p.workStatus === filterWorkStatus.value)
  if (filterPaymentStatus.value) list = list.filter((p) => p.paymentStatus === filterPaymentStatus.value)
  if (q) {
    list = list.filter((p) => {
      const site = p.siteId ? (siteMap.value[p.siteId] || '') : ''
      return (
        String(p.description || '').toLowerCase().includes(q) ||
        String(p.prestataireName || '').toLowerCase().includes(q) ||
        site.toLowerCase().includes(q) ||
        String(p.amount).includes(q)
      )
    })
  }
  const field = sortField.value === 'prestataire' ? 'prestataireName' : sortField.value
  return sortByField(list, field, sortOrder.value)
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const [list, prestataires, sites] = await Promise.all([
      listAllPrestations(),
      listPrestataires(),
      listSites(),
    ])
    items.value = list
    prestataireOptions.value = prestataires.map((p) => ({
      label: p.name || `${p.prenom} ${p.nom}`,
      value: p.id,
    }))
    siteOptions.value = sites.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.id }))
    siteMap.value = Object.fromEntries(sites.map((s) => [s.id, s.title]))
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les prestations.'
  } finally {
    loading.value = false
  }
}

async function reload() {
  reloading.value = true
  try {
    await load()
  } finally {
    reloading.value = false
  }
}

onMounted(load)

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    prestataireId: item.prestataireId,
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

function openPrestataire(item) {
  if (item?.prestataireId) {
    router.push({ name: 'prestataire-detail', params: { id: item.prestataireId } })
  }
}

function buildMenuItems(item) {
  const menu = []
  menu.push({ label: 'Voir prestataire', icon: 'pi pi-user', command: () => openPrestataire(item) })
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
    menu.push({ label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', command: () => askDelete(item) })
  }
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
    else await createPrestation(form.value.prestataireId, payload)
    dialog.value = false
    await load()
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
    await load()
    toast.add({ severity: 'success', summary: 'Paiement', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Paiement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: changingStatus, run: saveStatus } = useAsyncAction(async () => {
  try {
    await changePrestationStatus(currentItem.value.id, statusForm.value.workStatus)
    statusDialog.value = false
    await load()
    toast.add({ severity: 'success', summary: 'Statut', detail: 'Mis à jour.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Statut', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { run: runDuplicate } = useAsyncAction(async (item) => {
  try {
    await duplicatePrestation(item.id)
    await load()
    toast.add({ severity: 'success', summary: 'Prestation', detail: 'Dupliquée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Prestation', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deletePrestation(item.id)
    await load()
    toast.add({ severity: 'success', summary: 'Prestation', detail: 'Supprimée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Prestation', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { run: runReset } = useAsyncAction(async (item) => {
  try {
    await resetPrestationPayments(item.id)
    await load()
    toast.add({ severity: 'success', summary: 'Paiements', detail: 'Réinitialisés.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Paiements', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <div class="prestation-list-panel">
    <AppTablePanelHeader
      title="Prestations"
      :count-label="`${filteredItems.length}`"
      create-label="Nouvelle prestation"
      :show-create="canCreate"
      :hide-create-on-mobile="isAppMobile"
      :sticky="isAppMobile"
      :reloading="reloading"
      show-search
      v-model:search-term="searchTerm"
      search-placeholder="Rechercher (prestataire, description…)"
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

    <AppTableState
      :loading="loading"
      :error="error"
      :is-empty="!loading && !error && filteredItems.length === 0"
      empty-title="Aucune prestation"
      empty-text="Aucune prestation ne correspond aux filtres."
      @retry="load"
    >
      <AppEntityDataView
        v-if="isAppMobile"
        :items="filteredItems"
        :title-of="(item) => item.description"
        :subtitle-of="(item) => item.prestataireName || null"
        :meta-of="(item) => `${formatMontant(item.amount, DEVISE_APP)} · ${formatMontant(item.paidAmount ?? 0, DEVISE_APP)} payé${item.siteId && siteMap[item.siteId] ? ` · ${siteMap[item.siteId]}` : ''}`"
        :status-of="(item) => ({ value: PAYMENT_STATUS_LABEL[item.paymentStatus] || item.paymentStatus, severity: PAYMENT_STATUS_SEVERITY[item.paymentStatus] })"
        :actions-of="buildMenuItems"
        :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
        @select="openPrestataire"
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
        v-else
        :value="filteredItems"
        paginator
        :rows="tableRows"
        striped-rows
        :sort-field="sortField === 'prestataire' ? 'prestataireName' : (sortField || undefined)"
        :sort-order="sortOrder"
        @row-contextmenu="onRowContextMenu"
      >
        <Column v-if="showIndex" header="#" style="width: 3.5rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column v-if="isColVisible('prestataire')" field="prestataireName" header="Prestataire" sortable>
          <template #body="{ data }">
            <button type="button" class="linkish" @click="openPrestataire(data)">
              {{ data.prestataireName || '—' }}
            </button>
          </template>
        </Column>
        <Column v-if="isColVisible('description')" field="description" header="Description" sortable />
        <Column v-if="isColVisible('site')" header="Site">
          <template #body="{ data }">{{ data.siteId ? (siteMap[data.siteId] || '—') : '—' }}</template>
        </Column>
        <Column v-if="isColVisible('amount')" field="amount" header="Montant" sortable>
          <template #body="{ data }">{{ formatMontant(data.amount, DEVISE_APP) }}</template>
        </Column>
        <Column v-if="isColVisible('paidAmount')" field="paidAmount" header="Payé" sortable>
          <template #body="{ data }">{{ formatMontant(data.paidAmount ?? 0, DEVISE_APP) }}</template>
        </Column>
        <Column v-if="isColVisible('workStatus')" field="workStatus" header="Statut" sortable>
          <template #body="{ data }">
            <Tag :value="WORK_STATUS_LABEL[data.workStatus] || data.workStatus" :severity="WORK_STATUS_SEVERITY[data.workStatus]" />
          </template>
        </Column>
        <Column v-if="isColVisible('paymentStatus')" field="paymentStatus" header="Paiement" sortable>
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
    </AppTableState>

    <Menu ref="actionMenu" :model="menuModel" popup />
    <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />

    <AppMobileFab
      v-if="isAppMobile && canCreate"
      aria-label="Nouvelle prestation"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="editingId ? 'Modifier prestation' : 'Nouvelle prestation'" modal style="width: min(520px, 95vw)">
      <div v-if="!editingId" class="field">
        <label>Prestataire <span class="required">*</span></label>
        <Select
          v-model="form.prestataireId"
          :options="prestataireOptions"
          option-label="label"
          option-value="value"
          filter
          :invalid="Boolean(fieldErrors.prestataireId)"
          fluid
        />
        <AppFieldError :message="fieldErrors.prestataireId" />
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
  </div>
</template>

<style scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}
.required {
  color: var(--p-red-500, #ef4444);
}
.linkish {
  border: none;
  background: none;
  padding: 0;
  color: var(--layout-accent-strong, var(--p-primary-color));
  font: inherit;
  cursor: pointer;
  text-align: left;
}
.linkish:hover {
  text-decoration: underline;
}
</style>
