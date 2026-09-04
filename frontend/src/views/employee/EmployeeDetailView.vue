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
import { getEmployee } from '@/domains/employee/services/employeeService'
import { listTasks } from '@/domains/task/services/taskService'
import { listSites } from '@/domains/site/services/siteService'
import { taskStatusLabel, taskStatusSeverity, formatDateFr } from '@/domains/shared/utils/entLabels'

const route = useRoute()
const router = useRouter()
const { isAppMobile } = useAppMobileLayout()

const employee = ref(null)
const tasks = ref([])
const siteMap = ref({})
const loading = ref(true)
const error = ref(null)
const activeTab = ref('0')

const employeeTabItems = computed(() => [
  { value: '0', label: 'Informations', shortLabel: 'Infos' },
  { value: '1', label: `Tâches (${tasks.value.length})`, shortLabel: 'Tâches' },
])

const infoItems = computed(() => {
  if (!employee.value) return []
  return [
    { key: 'email', label: 'Email', icon: 'pi pi-envelope', value: employee.value.email },
    { key: 'phone', label: 'Téléphone', icon: 'pi pi-phone', value: employee.value.phone },
    { key: 'role', label: 'Fonction', icon: 'pi pi-id-card', value: employee.value.roleCode || employee.value.function },
    { key: 'address', label: 'Adresse', icon: 'pi pi-map-marker', value: employee.value.address || null, full: true },
  ]
})

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
            <h1 class="detail-header__title">{{ employee.name || `${employee.prenom} ${employee.nom}` }}</h1>
            <Tag :value="employee.isEnabled ? 'Actif' : 'Inactif'" />
          </div>
          <Button label="Retour" icon="pi pi-arrow-left" text @click="router.push({ name: 'employees' })" />
        </div>
      </template>
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="activeTab"
          :items="employeeTabItems"
        />
        <Tabs v-model:value="activeTab">
          <TabList v-if="!isAppMobile">
            <Tab value="0">Informations</Tab>
            <Tab value="1">Tâches ({{ tasks.length }})</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <AppDetailInfoList :items="infoItems" />
            </TabPanel>
            <TabPanel value="1">
              <AppEntityDataView
                v-if="isAppMobile && tasks.length"
                :items="tasks"
                :title-of="(item) => item.title"
                :subtitle-of="(item) => siteMap[item.siteId] || null"
                :meta-of="(item) => formatDateFr(item.dateDue)"
                :status-of="(item) => ({ value: taskStatusLabel(item.status), severity: taskStatusSeverity(item.status) })"
              />
              <DataTable v-else-if="tasks.length" :value="tasks" striped-rows>
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
}

.detail-header__title {
  margin: 0 0 0.35rem;
  font-size: 1.25rem;
}

.dashboard-page__state {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
