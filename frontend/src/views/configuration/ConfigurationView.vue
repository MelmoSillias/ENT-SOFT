<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Message from 'primevue/message'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import AppThemeControls from '@/domains/layout/components/AppThemeControls.vue'
import AgencySettingsPanel from '@/views/configuration/AgencySettingsPanel.vue'
import ImpressionSettingsPanel from '@/views/configuration/ImpressionSettingsPanel.vue'
import NumerotationSettingsPanel from '@/views/configuration/NumerotationSettingsPanel.vue'
import CorbeillePanel from '@/views/configuration/CorbeillePanel.vue'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

const route = useRoute()
const router = useRouter()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const activeTab = ref('appearance')
const corbeillePanel = ref(null)

const canViewSettings = computed(() => hasPermission('configuration.settings.update'))
const canEditSettings = computed(() => hasPermission('configuration.settings.update'))

const tabItems = computed(() => {
  const items = [{ value: 'appearance', label: 'Apparence', shortLabel: 'Apparence' }]
  if (canViewSettings.value) {
    items.push(
      { value: 'agency', label: 'Agence', shortLabel: 'Agence' },
      { value: 'impressions', label: 'Impressions', shortLabel: 'Impress.' },
      { value: 'settings', label: 'Paramètres', shortLabel: 'Params' },
      { value: 'corbeille', label: 'Corbeille', shortLabel: 'Corbeille' },
    )
  }
  return items
})

function syncFromQuery() {
  const tab = route.query.tab
  if (tab === 'agency' && canViewSettings.value) {
    activeTab.value = 'agency'
  } else if (tab === 'impressions' && canViewSettings.value) {
    activeTab.value = 'impressions'
  } else if (tab === 'settings' && canViewSettings.value) {
    activeTab.value = 'settings'
  } else if (tab === 'corbeille' && canViewSettings.value) {
    activeTab.value = 'corbeille'
  } else {
    activeTab.value = 'appearance'
  }
}

watch(activeTab, (tab) => {
  router.replace({ query: { ...route.query, tab } })
  if (tab === 'corbeille') corbeillePanel.value?.load()
})

syncFromQuery()
</script>

<template>
  <section class="dashboard-page configurations-page">
    <Card class="dashboard-panel">
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="activeTab"
          :items="tabItems"
        />
        <Tabs v-else v-model:value="activeTab">
          <TabList>
            <Tab value="appearance">Apparence</Tab>
            <Tab v-if="canViewSettings" value="agency">Agence</Tab>
            <Tab v-if="canViewSettings" value="impressions">Impressions</Tab>
            <Tab v-if="canViewSettings" value="settings">Paramètres</Tab>
            <Tab v-if="canViewSettings" value="corbeille">Corbeille</Tab>
          </TabList>

          <TabPanels>
            <TabPanel value="appearance" />
            <TabPanel v-if="canViewSettings" value="agency" />
            <TabPanel v-if="canViewSettings" value="impressions" />
            <TabPanel v-if="canViewSettings" value="settings" />
            <TabPanel v-if="canViewSettings" value="corbeille" />
          </TabPanels>
        </Tabs>

        <div v-show="activeTab === 'appearance'">
          <AppThemeControls />
        </div>
        <div v-if="canViewSettings" v-show="activeTab === 'agency'">
          <AgencySettingsPanel :can-edit="canEditSettings" />
        </div>
        <div v-if="canViewSettings" v-show="activeTab === 'impressions'">
          <ImpressionSettingsPanel :can-edit="canEditSettings" />
        </div>
        <div v-if="canViewSettings" v-show="activeTab === 'settings'" class="settings-dedicated">
          <NumerotationSettingsPanel :can-edit="canEditSettings" />
        </div>
        <div v-if="canViewSettings" v-show="activeTab === 'corbeille'">
          <CorbeillePanel ref="corbeillePanel" />
        </div>

        <Message
          v-if="!canViewSettings"
          severity="info"
          class="configurations-page__info"
        >
          Seule la personnalisation de l'apparence est disponible pour votre profil.
        </Message>
      </template>
    </Card>
  </section>
</template>

<style scoped>
.configurations-page__info {
  margin-top: 1rem;
}

.settings-dedicated {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>
