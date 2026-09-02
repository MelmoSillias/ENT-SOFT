<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { getEquipment } from '@/domains/stock/services/equipmentService'
import { listStockMovements } from '@/domains/stock/services/stockMovementService'
import { listClients } from '@/domains/client/services/clientService'
import { formatDateFr } from '@/domains/shared/utils/entLabels'

const route = useRoute()
const router = useRouter()

const equipment = ref(null)
const movements = ref([])
const clientMap = ref({})
const loading = ref(true)
const error = ref(null)

const equipmentMovements = computed(() =>
  movements.value.filter((m) => m.lines?.some((l) => l.equipmentId === equipment.value?.id)),
)

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
        <TabView>
          <TabPanel header="Informations">
            <dl class="detail-dl">
              <div><dt>Description</dt><dd>{{ equipment.description || '—' }}</dd></div>
              <div><dt>Client</dt><dd>{{ clientMap[equipment.clientId] || '—' }}</dd></div>
            </dl>
          </TabPanel>
          <TabPanel :header="`Mouvements de stock (${equipmentMovements.length})`">
            <DataTable v-if="equipmentMovements.length" :value="equipmentMovements" striped-rows>
              <Column header="Date">
                <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
              </Column>
              <Column field="quantity" header="Quantité" />
              <Column field="unit" header="Unité" />
              <Column header="Client">
                <template #body="{ data }">{{ clientMap[data.clientId] || '—' }}</template>
              </Column>
            </DataTable>
            <p v-else class="dashboard-page__state">Aucun mouvement de stock.</p>
          </TabPanel>
        </TabView>
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
  width: 100%;
}

.detail-header__code {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--layout-text-muted);
  text-transform: uppercase;
}

.detail-header__title {
  margin: 0.25rem 0 0.5rem;
  font-size: 1.25rem;
}

.detail-dl {
  display: grid;
  gap: 0.75rem;
  margin: 0;
}

.detail-dl div {
  display: grid;
  grid-template-columns: 8rem 1fr;
  gap: 0.5rem;
}

.detail-dl dt {
  font-weight: 600;
  color: var(--layout-text-muted);
}

.detail-dl dd {
  margin: 0;
}

.dashboard-page__state {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
