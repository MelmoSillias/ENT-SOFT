<script setup>
import { computed, onMounted, ref } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import SiteFormFields from '@/domains/site/components/SiteFormFields.vue'
import { listSites, createSite, updateSite, deleteSite } from '@/domains/site/services/siteService'
import { listClients } from '@/domains/client/services/clientService'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const items = ref([])
const clientOptions = ref([])
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

const canCreate = computed(() => hasPermission('site.sites.create'))

function emptyForm() {
  return { title: '', description: '', clientId: null }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  return errs
})

async function loadClients() {
  const clients = await listClients()
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  clientMap.value = Object.fromEntries(clients.map((c) => [c.id, c.title]))
}

async function fetchItems() {
  items.value = await listSites()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadClients()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les sites.'
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
    [item.code, item.title, item.description, clientMap.value[item.clientId]].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
})

const countLabel = computed(() => `${filteredItems.value.length}`)
const dialogTitle = computed(() => (editingId.value ? 'Modifier site' : 'Nouveau site'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = { code: item.code, title: item.title ?? '', description: item.description ?? '', clientId: item.clientId }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('site.sites.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('site.sites.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer le site',
    message: `Supprimer « ${item.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteSite(item.id)
    toast.add({ severity: 'success', summary: 'Site', detail: 'Supprimé.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Site', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = { title: form.value.title.trim(), description: form.value.description || null, clientId: form.value.clientId || null }
  try {
    if (editingId.value) await updateSite(editingId.value, payload)
    else await createSite(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Site', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Site', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Sites"
          :count-label="countLabel"
          create-label="Nouveau site"
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
            <Column field="code" header="Code" />
            <Column field="title" header="Titre" />
            <Column header="Client">
              <template #body="{ data }">{{ clientMap[data.clientId] || '—' }}</template>
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
      <SiteFormFields v-model="form" :errors="fieldErrors" :client-options="clientOptions" :show-code="Boolean(editingId)" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
