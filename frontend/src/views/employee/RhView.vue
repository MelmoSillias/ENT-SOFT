<script setup>
import { computed, ref } from 'vue'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import EmployeeListPanel from '@/views/employee/EmployeeListPanel.vue'
import PrestataireListPanel from '@/views/employee/PrestataireListPanel.vue'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()
const canEmployees = computed(() => hasPermission('employee.employees.view'))
const canPrestataires = computed(() => hasPermission('employee.prestataires.view'))
const activeTab = ref(canEmployees.value ? '0' : '1')

const tabItems = computed(() => {
  const items = []
  if (canEmployees.value) items.push({ value: '0', label: 'Employés', shortLabel: 'Employés' })
  if (canPrestataires.value) items.push({ value: '1', label: 'Prestataires', shortLabel: 'Presta.' })
  return items
})
</script>

<template>
  <section class="dashboard-page">
    <AppMobileSegmentTabs
      v-if="isAppMobile && tabItems.length > 1"
      v-model="activeTab"
      :items="tabItems"
    />
    <Tabs v-else-if="!isAppMobile" v-model:value="activeTab">
      <TabList>
        <Tab v-if="canEmployees" value="0">Employés</Tab>
        <Tab v-if="canPrestataires" value="1">Prestataires</Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-if="canEmployees" value="0" />
        <TabPanel v-if="canPrestataires" value="1" />
      </TabPanels>
    </Tabs>

    <div v-if="canEmployees" v-show="activeTab === '0'">
      <EmployeeListPanel :fab-enabled="activeTab === '0'" />
    </div>
    <div v-if="canPrestataires" v-show="activeTab === '1'">
      <PrestataireListPanel :fab-enabled="activeTab === '1'" />
    </div>
  </section>
</template>
