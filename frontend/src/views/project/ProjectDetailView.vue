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
import { getProjectDetail } from '@/domains/project/services/projectService'
import ProjectSitesTable from '@/domains/project/components/ProjectSitesTable.vue'
import { formatDateFr, formatDateTimeFr, projectStatusLabel, projectStatusSeverity } from '@/domains/shared/utils/entLabels'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'

const route = useRoute()
const router = useRouter()

const project = ref(null)
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    project.value = await getProjectDetail(route.params.id)
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le projet.'
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

    <Card v-else-if="project" class="dashboard-panel">
      <template #title>
        <div class="detail-header">
          <div>
            <span class="detail-header__code">{{ project.code }}</span>
            <h1 class="detail-header__title">{{ project.title }}</h1>
            <Tag :value="projectStatusLabel(project.status)" :severity="projectStatusSeverity(project.status)" />
          </div>
          <Button label="Retour" icon="pi pi-arrow-left" text @click="router.push({ name: 'projects' })" />
        </div>
      </template>
      <template #content>
        <Tabs value="0">
          <TabList>
            <Tab value="0">Informations</Tab>
            <Tab value="1">Sites ({{ project.sites?.length ?? 0 }})</Tab>
            <Tab value="2">Événements ({{ project.events?.length ?? 0 }})</Tab>
            <Tab value="3">Documents</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <dl class="detail-dl">
                <div><dt>Client</dt><dd>{{ project.clientTitle || project.clientId }}</dd></div>
                <div><dt>Objet</dt><dd>{{ project.object || '—' }}</dd></div>
                <div><dt>Budget</dt><dd>{{ formatMontant(project.budget, DEVISE_APP) }}</dd></div>
                <div><dt>Date début</dt><dd>{{ formatDateFr(project.dateDebut) }}</dd></div>
                <div><dt>Date fin</dt><dd>{{ formatDateFr(project.dateFin) }}</dd></div>
              </dl>
            </TabPanel>
            <TabPanel value="1">
              <ProjectSitesTable
                :sites="project.sites"
                :sites-informations="project.sitesInformations"
                :lots="project.lots"
                :project-id="project.id"
              />
            </TabPanel>
            <TabPanel value="2">
              <DataTable v-if="project.events?.length" :value="project.events" striped-rows>
                <Column field="title" header="Titre" />
                <Column field="type" header="Type" />
                <Column header="Date">
                  <template #body="{ data }">{{ formatDateTimeFr(data.dateEvent) }}</template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">Aucun événement.</p>
            </TabPanel>
            <TabPanel value="3">
              <p class="dashboard-page__state">Gestion documentaire — bientôt disponible.</p>
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
