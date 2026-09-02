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
import EmployeeFormFields from '@/domains/employee/components/EmployeeFormFields.vue'
import { listEmployees, createEmployee, updateEmployee, deleteEmployee } from '@/domains/employee/services/employeeService'
import { hasRequiredText, requiredMessage, hasValidPhone } from '@/domains/shared/utils/formValidation'
import { sanitizePhoneInput } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const items = ref([])
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])

const canCreate = computed(() => hasPermission('employee.employees.create'))

function emptyForm() {
  return { name: '', email: '', phone: '', function: '', address: '' }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.name)) errs.name = requiredMessage('Nom')
  if (!hasRequiredText(form.value.email)) errs.email = requiredMessage('Email')
  if (!hasValidPhone(form.value.phone)) errs.phone = 'Téléphone invalide.'
  if (!hasRequiredText(form.value.function)) errs.function = requiredMessage('Fonction')
  return errs
})

async function fetchItems() {
  items.value = await listEmployees()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await fetchItems()
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
  if (!q) return items.value
  return items.value.filter((item) =>
    [item.name, item.email, item.phone, item.function].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
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
    name: item.name ?? '',
    email: item.email ?? '',
    phone: item.phone ?? '',
    function: item.function ?? '',
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
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer l\'employé',
    message: `Supprimer « ${item.name} » ?`,
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
    name: form.value.name.trim(),
    email: form.value.email.trim(),
    phone: sanitizePhoneInput(form.value.phone),
    function: form.value.function.trim(),
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
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Employés (RH)"
          :count-label="countLabel"
          create-label="Nouvel employé"
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
            <Column field="name" header="Nom" />
            <Column field="email" header="Email" />
            <Column field="phone" header="Téléphone" />
            <Column field="function" header="Fonction" />
            <Column header="Statut">
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
          <Menu ref="actionMenu" :model="menuModel" popup />
        </AppTableState>
      </template>
    </Card>

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(640px, 95vw)">
      <EmployeeFormFields v-model="form" :errors="fieldErrors" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
