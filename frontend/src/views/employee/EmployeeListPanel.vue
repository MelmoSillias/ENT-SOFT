<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import EmployeeFormFields from '@/domains/employee/components/EmployeeFormFields.vue'
import { listEmployees, createEmployee, updateEmployee, deleteEmployee } from '@/domains/employee/services/employeeService'
import { listRoles } from '@/domains/access/services/roleService'
import { hasRequiredText, requiredMessage, hasValidPhone, sanitizePhoneInput } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

defineProps({
  fabEnabled: {
    type: Boolean,
    default: true,
  },
})

const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const EMPLOYEE_COLUMNS = [
  { key: 'name', label: 'Nom', defaultVisible: true },
  { key: 'email', label: 'Email', defaultVisible: true },
  { key: 'phone', label: 'Téléphone', defaultVisible: true },
  { key: 'roleCode', label: 'Fonction', defaultVisible: true },
  { key: 'isEnabled', label: 'Statut', defaultVisible: true },
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
} = useTableSettings('table_employees', EMPLOYEE_COLUMNS, {
  defaultSortField: 'name',
})

const items = ref([])
const roleOptions = ref([])
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const rowContextMenu = ref()

const canCreate = computed(() => hasPermission('employee.employees.create'))

function emptyForm() {
  return { prenom: '', nom: '', email: '', phone: '', roleCode: '', address: '' }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.prenom)) errs.prenom = requiredMessage('Prénom')
  if (!hasRequiredText(form.value.nom)) errs.nom = requiredMessage('Nom')
  if (!hasRequiredText(form.value.email)) errs.email = requiredMessage('Email')
  if (!hasValidPhone(form.value.phone)) errs.phone = 'Téléphone invalide.'
  if (!hasRequiredText(form.value.roleCode)) errs.roleCode = requiredMessage('Fonction')
  return errs
})

const roleLabel = (code) => roleOptions.value.find((r) => r.value === code)?.label ?? code

async function fetchItems() {
  items.value = await listEmployees()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [emps, roles] = await Promise.all([listEmployees(), listRoles({ enabledOnly: true })])
    items.value = emps
    roleOptions.value = roles.map((r) => ({ label: r.libelle, value: r.code }))
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les employés.'
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
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [item.name, item.prenom, item.nom, item.email, item.phone, item.roleCode]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(q),
    )
  }
  const field = sortField.value === 'name' ? 'nom' : sortField.value
  return sortByField(list, field, sortOrder.value)
})

const countLabel = computed(() => `${filteredItems.value.length}`)
const dialogTitle = computed(() => (editingId.value ? 'Modifier employé' : 'Nouvel employé'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    prenom: item.prenom ?? '',
    nom: item.nom ?? '',
    email: item.email ?? '',
    phone: item.phone ?? '',
    roleCode: item.roleCode ?? item.function ?? '',
    address: item.address ?? '',
  }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = [
    { label: 'Voir le détail', icon: 'pi pi-eye', command: () => router.push({ name: 'employee-detail', params: { id: item.id } }) },
  ]
  if (hasPermission('employee.employees.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('employee.employees.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
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
    header: 'Supprimer l\'employé',
    message: `Supprimer « ${item.name || `${item.prenom} ${item.nom}`} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteEmployee(item.id)
    toast.add({ severity: 'success', summary: 'Employé', detail: 'Supprimé.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Employé', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    prenom: form.value.prenom.trim(),
    nom: form.value.nom.trim(),
    email: form.value.email.trim(),
    phone: sanitizePhoneInput(form.value.phone),
    roleCode: form.value.roleCode,
    address: form.value.address || null,
  }
  try {
    if (editingId.value) await updateEmployee(editingId.value, payload)
    else await createEmployee(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Employé', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Employé', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <Card class="dashboard-panel">
    <template #title>
      <AppTablePanelHeader
        title="Employés"
        :count-label="countLabel"
        create-label="Nouvel employé"
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
          />
        </template>
      </AppTablePanelHeader>
    </template>
    <template #content>
      <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
        <AppEntityDataView
          v-if="isAppMobile"
          :items="filteredItems"
          :title-of="(item) => item.name || `${item.prenom} ${item.nom}`"
          :subtitle-of="(item) => item.email || null"
          :meta-of="(item) => roleLabel(item.roleCode || item.function)"
          :status-of="(item) => ({ value: item.isEnabled ? 'Actif' : 'Inactif', severity: item.isEnabled ? 'success' : 'secondary' })"
          :actions-of="buildMenuItems"
          :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
          @select="(item) => router.push({ name: 'employee-detail', params: { id: item.id } })"
        />
        <DataTable
          v-else
          :value="filteredItems"
          paginator
          :rows="tableRows"
          striped-rows
          :sort-field="sortField === 'name' ? 'nom' : (sortField || undefined)"
          :sort-order="sortOrder"
          @row-contextmenu="onRowContextMenu"
        >
          <Column v-if="showIndex" header="#" style="width: 3.5rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column v-if="isColVisible('name')" header="Nom" sortable field="nom">
            <template #body="{ data }">{{ data.name || `${data.prenom} ${data.nom}` }}</template>
          </Column>
          <Column v-if="isColVisible('email')" field="email" header="Email" sortable />
          <Column v-if="isColVisible('phone')" field="phone" header="Téléphone" sortable />
          <Column v-if="isColVisible('roleCode')" header="Fonction" sortable field="roleCode">
            <template #body="{ data }">{{ roleLabel(data.roleCode || data.function) }}</template>
          </Column>
          <Column v-if="isColVisible('isEnabled')" header="Statut" sortable field="isEnabled">
            <template #body="{ data }">
              <Tag :value="data.isEnabled ? 'Actif' : 'Inactif'" :severity="data.isEnabled ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column header="Actions" style="width: 5rem">
            <template #body="{ data }">
              <Button icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
            </template>
          </Column>
        </DataTable>
        <Menu v-if="!isAppMobile" ref="actionMenu" :model="menuModel" popup />
        <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
      </AppTableState>
    </template>
  </Card>

  <AppMobileFab
    v-if="isAppMobile && canCreate && fabEnabled"
    aria-label="Nouvel employé"
    @click="openCreate"
  />

  <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(640px, 95vw)">
    <EmployeeFormFields v-model="form" :errors="fieldErrors" :role-options="roleOptions" />
    <template #footer>
      <Button label="Annuler" severity="secondary" text :disabled="saving || deleting" @click="dialog = false" />
      <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
    </template>
  </Dialog>
</template>
