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
import AppThemeControls from '@/domains/layout/components/AppThemeControls.vue'
import AgencySettingsPanel from '@/views/configuration/AgencySettingsPanel.vue'
import ImpressionSettingsPanel from '@/views/configuration/ImpressionSettingsPanel.vue'
import NumerotationSettingsPanel from '@/views/configuration/NumerotationSettingsPanel.vue'
import CorbeillePanel from '@/views/configuration/CorbeillePanel.vue'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

const route = useRoute()
const router = useRouter()
const { hasPermission } = usePermissions()

const activeTab = ref('appearance')
const corbeillePanel = ref(null)

const canViewSettings = computed(() => hasPermission('configuration.settings.update'))
const canEditSettings = computed(() => hasPermission('configuration.settings.update'))

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
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="appearance">Apparence</Tab>
            <Tab v-if="canViewSettings" value="agency">Agence</Tab>
            <Tab v-if="canViewSettings" value="impressions">Impressions</Tab>
            <Tab v-if="canViewSettings" value="settings">Paramètres</Tab>
            <Tab v-if="canViewSettings" value="corbeille">Corbeille</Tab>
          </TabList>

          <TabPanels>
            <TabPanel value="appearance">
              <AppThemeControls />
            </TabPanel>

            <TabPanel v-if="canViewSettings" value="agency">
              <AgencySettingsPanel :can-edit="canEditSettings" />
            </TabPanel>

            <TabPanel v-if="canViewSettings" value="impressions">
              <ImpressionSettingsPanel :can-edit="canEditSettings" />
            </TabPanel>

            <TabPanel v-if="canViewSettings" value="settings">
              <div class="settings-dedicated">
                <NumerotationSettingsPanel :can-edit="canEditSettings" />
              </div>
            </TabPanel>

            <TabPanel v-if="canViewSettings" value="corbeille">
              <CorbeillePanel ref="corbeillePanel" />
            </TabPanel>
          </TabPanels>
        </Tabs>

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
  margin-bottom: 1.25rem;
}
</style>
