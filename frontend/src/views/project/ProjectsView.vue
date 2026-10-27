<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import ProjectListView from '@/views/project/ProjectListView.vue'
import ProjectReportListPanel from '@/views/project/ProjectReportListPanel.vue'

const route = useRoute()
const router = useRouter()
const { isAppMobile } = useAppMobileLayout()
const { hasPermission } = usePermissions()
const activeTab = ref('projects')
const canViewReports = computed(() => hasPermission('project.reports.view'))
const tabItems = computed(() => [
  { value: 'projects', label: 'Projets', shortLabel: 'Projets' },
  ...(canViewReports.value ? [{ value: 'reports', label: 'Rapports', shortLabel: 'Rapports' }] : []),
])

watch(() => route.query.tab, (tab) => {
  activeTab.value = tab === 'reports' && canViewReports.value ? 'reports' : 'projects'
}, { immediate: true })

watch(activeTab, (tab) => {
  if (route.query.tab !== tab) router.replace({ query: { ...route.query, tab } })
})
</script>

<template>
  <section class="dashboard-page projects-view">
    <Card class="dashboard-panel projects-view__tabs">
      <template #content>
        <AppMobileSegmentTabs v-if="isAppMobile" v-model="activeTab" :items="tabItems" />
        <Tabs v-else v-model:value="activeTab">
          <TabList>
            <Tab value="projects">Projets</Tab>
            <Tab v-if="canViewReports" value="reports">Rapports</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="projects" />
            <TabPanel v-if="canViewReports" value="reports" />
          </TabPanels>
        </Tabs>
      </template>
    </Card>
    <ProjectListView v-if="activeTab === 'projects'" />
    <Card v-else class="dashboard-panel">
      <template #content>
        <ProjectReportListPanel />
      </template>
    </Card>
  </section>
</template>

<style scoped>
.projects-view {
  gap: 0.75rem;
}

.projects-view__tabs :deep(.p-card-body),
.projects-view__tabs :deep(.p-card-content) {
  padding-bottom: 0;
}
</style>
