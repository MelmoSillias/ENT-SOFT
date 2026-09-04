<script setup>
import { computed, onMounted, ref } from 'vue'
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
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppDetailInfoList from '@/domains/shared/components/AppDetailInfoList.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { getEquipment } from '@/domains/stock/services/equipmentService'
import { listStockMovements } from '@/domains/stock/services/stockMovementService'
import { listClients } from '@/domains/client/services/clientService'
import { formatDateFr, stockDirectionLabel, stockDirectionSeverity, equipmentUnitLabel } from '@/domains/shared/utils/entLabels'

const route = useRoute()
const router = useRouter()
const { isAppMobile } = useAppMobileLayout()

const equipment = ref(null)
const movements = ref([])
const clientMap = ref({})
const loading = ref(true)
const error = ref(null)
const activeTab = ref('0')

const equipmentMovements = computed(() =>
  movements.value.filter((m) => m.lines?.some((l) => l.equipmentId === equipment.value?.id)),
)

const equipmentTabItems = computed(() => [
  { value: '0', label: 'Informations', shortLabel: 'Infos' },
  { value: '1', label: `Mouvements (${equipmentMovements.value.length})`, shortLabel: 'Mouv.' },
])

const infoItems = computed(() => {
  if (!equipment.value) return []
  return [
    { key: 'description', label: 'Description', icon: 'pi pi-align-left', value: equipment.value.description || null, full: true },
    { key: 'quantity', label: 'Quantité', icon: 'pi pi-box', value: `${equipment.value.quantity ?? 0} ${equipmentUnitLabel(equipment.value.unit)}` },
    { key: 'unit', label: 'Unité', icon: 'pi pi-tag', value: equipmentUnitLabel(equipment.value.unit) },
    { key: 'client', label: 'Client', icon: 'pi pi-building', value: clientMap.value[equipment.value.clientId] || null },
  ]
})

function lineQuantity(item) {
  const line = item.lines?.find((l) => l.equipmentId === equipment.value?.id)
  return line?.quantity ?? item.quantity
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [eq, allMovements, clients] = await Promise.all([
      getEquipment(route.params.id),
      listStockMovements(),
      listClients(),
    ])
    equipment.value = eq
    movements.value = allMovements
    clientMap.value = Object.fromEntries(clients.map((c) => [c.id, c.title]))
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger l\'équipement.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="dashboard-page">
    <div v-if="loading" class="dashboard-page__state">Chargement…</div>
    <div v-else-if="error" class="dashboard-page__state">{{ error }}</div>

    <Card v-else-if="equipment" class="dashboard-panel">
      <template #title>
        <div class="detail-header">
          <div>
            <span class="detail-header__code">{{ equipment.code }}</span>
            <h1 class="detail-header__title">{{ equipment.title }}</h1>
            <Tag :value="equipment.isEnabled ? 'Actif' : 'Inactif'" />
          </div>
          <Button label="Retour" icon="pi pi-arrow-left" text @click="router.push({ name: 'equipments' })" />
        </div>
      </template>
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="activeTab"
          :items="equipmentTabItems"
        />
        <Tabs v-model:value="activeTab">
          <TabList v-if="!isAppMobile">
            <Tab value="0">Informations</Tab>
            <Tab value="1">Mouvements de stock ({{ equipmentMovements.length }})</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <AppDetailInfoList :items="infoItems" />
            </TabPanel>
            <TabPanel value="1">
              <AppEntityDataView
                v-if="isAppMobile && equipmentMovements.length"
                :items="equipmentMovements"
                :title-of="(item) => `${lineQuantity(item)} ${equipmentUnitLabel(equipment.unit)}`"
                :meta-of="(item) => formatDateFr(item.date)"
                :status-of="(item) => ({ value: stockDirectionLabel(item.direction), severity: stockDirectionSeverity(item.direction) })"
              />
              <DataTable v-else-if="equipmentMovements.length" :value="equipmentMovements" striped-rows>
                <Column header="Date">
                  <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
                </Column>
                <Column header="Type">
                  <template #body="{ data }">
                    <Tag :value="stockDirectionLabel(data.direction)" :severity="stockDirectionSeverity(data.direction)" />
                  </template>
                </Column>
                <Column header="Quantité">
                  <template #body="{ data }">{{ lineQuantity(data) }} {{ equipmentUnitLabel(equipment.unit) }}</template>
                </Column>
                <Column header="Client">
                  <template #body="{ data }">{{ clientMap[data.clientId] || '—' }}</template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">Aucun mouvement de stock.</p>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </section>
</template>

<style scoped>
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.detail-header__code {
  font-size: 0.8rem;
  color: var(--layout-text-muted);
  font-weight: 600;
}

.detail-header__title {
  margin: 0.15rem 0 0.35rem;
  font-size: 1.25rem;
}

.dashboard-page__state {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
