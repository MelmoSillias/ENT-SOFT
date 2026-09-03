<script setup>
import { onMounted, ref } from 'vue'
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
import { getEmployee } from '@/domains/employee/services/employeeService'
import { listTasks } from '@/domains/task/services/taskService'
import { listSites } from '@/domains/site/services/siteService'
import { taskStatusLabel, taskStatusSeverity, formatDateFr } from '@/domains/shared/utils/entLabels'

const route = useRoute()
const router = useRouter()

const employee = ref(null)
const tasks = ref([])
const siteMap = ref({})
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const [emp, allTasks, sites] = await Promise.all([
      getEmployee(route.params.id),
      listTasks({ employeeId: route.params.id }),
      listSites(),
    ])
    employee.value = emp
    tasks.value = allTasks
    siteMap.value = Object.fromEntries(sites.map((s) => [s.id, s.title]))
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger l\'employé.'
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

    <Card v-else-if="employee" class="dashboard-panel">
      <template #title>
        <div class="detail-header">
          <div>
            <h1 class="detail-header__title">{{ employee.name }}</h1>
            <Tag :value="employee.isEnabled ? 'Actif' : 'Inactif'" />
          </div>
          <Button label="Retour" icon="pi pi-arrow-left" text @click="router.push({ name: 'employees' })" />
        </div>
      </template>
      <template #content>
        <Tabs value="0">
          <TabList>
            <Tab value="0">Informations</Tab>
            <Tab value="1">Tâches ({{ tasks.length }})</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <dl class="detail-dl">
                <div><dt>Email</dt><dd>{{ employee.email }}</dd></div>
                <div><dt>Téléphone</dt><dd>{{ employee.phone }}</dd></div>
                <div><dt>Fonction</dt><dd>{{ employee.function }}</dd></div>
                <div><dt>Adresse</dt><dd>{{ employee.address || '—' }}</dd></div>
              </dl>
            </TabPanel>
            <TabPanel value="1">
              <DataTable v-if="tasks.length" :value="tasks" striped-rows>
                <Column field="title" header="Titre" />
                <Column header="Site">
                  <template #body="{ data }">{{ siteMap[data.siteId] || '—' }}</template>
                </Column>
                <Column header="Échéance">
                  <template #body="{ data }">{{ formatDateFr(data.dateDue) }}</template>
                </Column>
                <Column header="Statut">
                  <template #body="{ data }">
                    <Tag :value="taskStatusLabel(data.status)" :severity="taskStatusSeverity(data.status)" />
                  </template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">Aucune tâche assignée.</p>
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
  width: 100%;
}

.detail-header__title {
  margin: 0 0 0.5rem;
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
