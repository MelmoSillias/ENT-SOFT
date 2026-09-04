<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import EquipmentFormFields from '@/domains/stock/components/EquipmentFormFields.vue'
import StockMovementListPanel from '@/views/stock/StockMovementListPanel.vue'
import { listEquipment, createEquipment, updateEquipment, deleteEquipment } from '@/domains/stock/services/equipmentService'
import { listClients } from '@/domains/client/services/clientService'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { equipmentUnitLabel } from '@/domains/shared/utils/entLabels'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const items = ref([])
const clientOptions = ref([])
const clientMap = ref({})
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const stockTab = ref('list')
const editingId = ref(null)
const actionItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const movementsPanel = ref(null)

const stockTabItems = [
  { value: 'list', label: 'Liste', shortLabel: 'Liste' },
  { value: 'movements', label: 'Mouvements', shortLabel: 'Mouv.' },
]

const canCreate = computed(() => hasPermission('stock.equipment.create'))
const canCreateMovement = computed(() => hasPermission('stock.movements.create'))

function emptyForm() {
  return { title: '', description: '', unit: 'unit', clientId: null }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  if (!form.value.unit) errs.unit = requiredMessage('Unité')
  return errs
})

async function loadClients() {
  const clients = await listClients()
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  clientMap.value = Object.fromEntries(clients.map((c) => [c.id, c.title]))
}

async function fetchItems() {
  items.value = await listEquipment()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadClients()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le matériel.'
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
    [
      item.code,
      item.title,
      item.description,
      equipmentUnitLabel(item.unit),
      clientMap.value[item.clientId],
    ].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
})

const countLabel = computed(() => `${filteredItems.value.length}`)
const dialogTitle = computed(() => (editingId.value ? 'Modifier équipement' : 'Nouvel équipement'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    code: item.code,
    title: item.title ?? '',
    description: item.description ?? '',
    unit: item.unit ?? 'unit',
    clientId: item.clientId,
  }
  resetErrors()
  dialog.value = true
}

async function openMovement(item, direction) {
  stockTab.value = 'movements'
  await nextTick()
  movementsPanel.value?.openCreate({
    equipmentId: item.id,
    direction,
    clientId: item.clientId ?? null,
  })
}

function buildMenuItems(item) {
  const menu = [
    { label: 'Voir le détail', icon: 'pi pi-eye', command: () => router.push({ name: 'equipment-detail', params: { id: item.id } }) },
  ]
  if (canCreateMovement.value) {
    menu.push({ label: 'Entrée de stock', icon: 'pi pi-sign-in', command: () => openMovement(item, 'in') })
    menu.push({ label: 'Sortie de stock', icon: 'pi pi-sign-out', command: () => openMovement(item, 'out') })
  }
  if (hasPermission('stock.equipment.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('stock.equipment.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer l\'équipement',
    message: `Supprimer « ${item.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteEquipment(item.id)
    toast.add({ severity: 'success', summary: 'Équipement', detail: 'Supprimé.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Équipement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    title: form.value.title.trim(),
    description: form.value.description || null,
    unit: form.value.unit,
    clientId: form.value.clientId || null,
  }
  try {
    if (editingId.value) await updateEquipment(editingId.value, payload)
    else await createEquipment(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Équipement', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Équipement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

function quantityDisplay(item) {
  return `${item.quantity ?? 0} ${equipmentUnitLabel(item.unit)}`
}
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="stockTab"
          :items="stockTabItems"
        />
        <Tabs v-else v-model:value="stockTab">
          <TabList>
            <Tab value="list">Liste</Tab>
            <Tab value="movements">Mouvements</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="list" />
            <TabPanel value="movements" />
          </TabPanels>
        </Tabs>

        <div v-show="stockTab === 'list'">
          <AppTablePanelHeader
            title="Matériels et équipements"
            :count-label="countLabel"
            create-label="Nouvel équipement"
            :show-create="canCreate"
            :hide-create-on-mobile="isAppMobile"
            :sticky="isAppMobile"
            :reloading="reloading"
            show-search
            v-model:search-term="searchTerm"
            search-placeholder="Rechercher…"
            @create="openCreate"
            @reload="reload"
          />
          <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
            <AppEntityDataView
              v-if="isAppMobile"
              :items="filteredItems"
              :title-of="(item) => item.title"
              :code-of="(item) => item.code"
              :subtitle-of="(item) => quantityDisplay(item)"
              :meta-of="(item) => clientMap[item.clientId] || item.description || null"
              :actions-of="buildMenuItems"
              @select="(item) => router.push({ name: 'equipment-detail', params: { id: item.id } })"
            />
            <DataTable v-else :value="filteredItems" paginator :rows="10" striped-rows>
              <Column field="code" header="Code" />
              <Column field="title" header="Titre" />
              <Column header="Quantité">
                <template #body="{ data }">{{ data.quantity ?? 0 }}</template>
              </Column>
              <Column header="Unité">
                <template #body="{ data }">{{ equipmentUnitLabel(data.unit) }}</template>
              </Column>
              <Column header="Client">
                <template #body="{ data }">{{ clientMap[data.clientId] || '—' }}</template>
              </Column>
              <Column header="Actions" style="width: 5rem">
                <template #body="{ data }">
                  <Button icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
                </template>
              </Column>
            </DataTable>
            <Menu v-if="!isAppMobile" ref="actionMenu" :model="menuModel" popup />
          </AppTableState>
        </div>

        <div v-show="stockTab === 'movements'">
          <StockMovementListPanel ref="movementsPanel" @changed="fetchItems" />
        </div>
      </template>
    </Card>

    <AppMobileFab
      v-if="isAppMobile && canCreate && stockTab === 'list'"
      aria-label="Nouvel équipement"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(640px, 95vw)">
      <EquipmentFormFields v-model="form" :errors="fieldErrors" :client-options="clientOptions" :show-code="Boolean(editingId)" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
