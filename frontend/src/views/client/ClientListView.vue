<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import Dialog from 'primevue/dialog'
import ClientFormFields from '@/domains/client/components/ClientFormFields.vue'
import { listClients, createClient, updateClient, deleteClient } from '@/domains/client/services/clientService'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
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

const canCreate = computed(() => hasPermission('client.clients.create'))

function emptyForm() {
  return { title: '', description: '' }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  return errs
})

async function fetchItems() {
  items.value = await listClients()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await fetchItems()
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les clients.'
  } finally {
    loading.value = false
  }
}

async function reload() {
  reloading.value = true
  try {
    await fetchItems()
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les clients.'
  } finally {
    reloading.value = false
  }
}

onMounted(load)

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  if (!q) return items.value
  return items.value.filter((item) =>
    [item.code, item.title, item.description].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
})

const countLabel = computed(() => {
  const total = filteredItems.value.length
  const base = items.value.length
  return searchTerm.value.trim() ? `${total} / ${base}` : `${total}`
})

const dialogTitle = computed(() => (editingId.value ? 'Modifier client' : 'Nouveau client'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = { title: item.title ?? '', description: item.description ?? '', code: item.code }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = [
    { label: 'Voir le détail', icon: 'pi pi-eye', command: () => router.push({ name: 'client-detail', params: { id: item.id } }) },
  ]
  if (hasPermission('client.clients.update')) {
    menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  }
  if (hasPermission('client.clients.delete')) {
    menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  }
  return menu
}

function toggleMenu(event, item) {
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer le client',
    message: `Supprimer le client « ${item.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteClient(item.id)
    toast.add({ severity: 'success', summary: 'Client', detail: 'Client supprimé.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Client', detail: e.response?.data?.error || 'Suppression impossible.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = { title: form.value.title.trim(), description: form.value.description || null }
  try {
    if (editingId.value) {
      await updateClient(editingId.value, payload)
    } else {
      await createClient(payload)
    }
    dialog.value = false
    form.value = emptyForm()
    resetErrors()
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Client', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Client', detail: e.response?.data?.error || 'Enregistrement impossible.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Clients"
          :count-label="countLabel"
          create-label="Nouveau client"
          :show-create="canCreate"
          :reloading="reloading"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher code, titre…"
          @create="openCreate"
          @reload="reload"
        />
      </template>
      <template #content>
        <AppTableState
          :loading="loading"
          :error="error"
          :is-empty="!loading && !error && filteredItems.length === 0"
          empty-title="Aucun client"
          empty-text="Ajoutez un client pour commencer."
          @retry="load"
        >
          <DataTable :value="filteredItems" paginator :rows="10" striped-rows>
            <Column field="code" header="Code" />
            <Column field="title" header="Titre" />
            <Column header="Description">
              <template #body="{ data }">
                <span class="cell-muted">{{ data.description || '—' }}</span>
              </template>
            </Column>
            <Column header="Statut">
              <template #body="{ data }">
                <Tag :value="data.isEnabled ? 'Actif' : 'Inactif'" :severity="data.isEnabled ? 'success' : 'secondary'" />
              </template>
            </Column>
            <Column header="Actions" style="width: 5rem">
              <template #body="{ data }">
                <Button
                  icon="pi pi-ellipsis-v"
                  text
                  rounded
                  aria-label="Actions"
                  :loading="deleting && actionItem?.id === data.id"
                  @click="toggleMenu($event, data)"
                />
              </template>
            </Column>
          </DataTable>
          <Menu ref="actionMenu" :model="menuModel" popup />
        </AppTableState>
      </template>
    </Card>

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(640px, 95vw)">
      <ClientFormFields v-model="form" :errors="fieldErrors" :show-code="Boolean(editingId)" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button
          :label="editingId ? 'Enregistrer' : 'Créer'"
          icon="pi pi-check"
          :loading="saving"
          :disabled="saving"
          @click="saveItem"
        />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.cell-muted {
  color: var(--layout-text-muted);
  font-size: 0.875rem;
}
</style>
