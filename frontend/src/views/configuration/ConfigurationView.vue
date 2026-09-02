<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Message from 'primevue/message'
import AppThemeControls from '@/domains/layout/components/AppThemeControls.vue'
import AgencySettingsPanel from '@/views/configuration/AgencySettingsPanel.vue'
import ImpressionSettingsPanel from '@/views/configuration/ImpressionSettingsPanel.vue'
import NumerotationSettingsPanel from '@/views/configuration/NumerotationSettingsPanel.vue'
import PaysDevisesPanel from '@/views/configuration/PaysDevisesPanel.vue'
import TauxHistoriquePanel from '@/views/configuration/TauxHistoriquePanel.vue'
import CorbeillePanel from '@/views/configuration/CorbeillePanel.vue'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const { hasPermission, hasAnyPermission } = usePermissions()

const TELEPHONE_DOUBLON_KEY = 'TELEPHONE_DOUBLON_COMPORTEMENT'
const CLIENT_AUTORISER_SOLDE_NEGATIF_KEY = 'CLIENT_AUTORISER_SOLDE_NEGATIF'

const telephoneDoublonOptions = [
  { value: 'BLOQUER', label: 'Toujours bloquer' },
  { value: 'AUTO_SELECT', label: 'Sélection automatique' },
]

function parseBoolSetting(value) {
  return ['1', 'true', 'yes', 'on'].includes(String(value ?? '').toLowerCase().trim())
}

const activeTab = ref('appearance')
const settingsLoading = ref(false)
const settingsError = ref(null)
const telephoneDoublon = ref('BLOQUER')
const savingTelephoneDoublon = ref(false)
const autoriserSoldeNegatif = ref(false)
const savingAutoriserSoldeNegatif = ref(false)
const tauxHistoriquePanel = ref(null)
const corbeillePanel = ref(null)

const canViewSettings = computed(() => hasPermission('configuration.settings.update'))
const canEditSettings = computed(() => hasPermission('configuration.settings.update'))
const canViewReferentiel = computed(() =>
  hasAnyPermission('referentiel.devises.view', 'referentiel.devises.manage', 'referentiel.pays.view', 'referentiel.pays.manage'),
)
const canEditReferentiel = computed(() =>
  hasAnyPermission('referentiel.pays.manage', 'referentiel.devises.manage'),
)

async function loadSettings() {
  if (!canViewSettings.value) return
  settingsLoading.value = true
  settingsError.value = null
  try {
    const { data } = await api.get('/settings')
    const all = data.items ?? data
    const doublon = all.find((s) => s.cle === TELEPHONE_DOUBLON_KEY)
    telephoneDoublon.value = doublon?.valeur === 'AUTO_SELECT' ? 'AUTO_SELECT' : 'BLOQUER'
    const soldeNegatif = all.find((s) => s.cle === CLIENT_AUTORISER_SOLDE_NEGATIF_KEY)
    autoriserSoldeNegatif.value = parseBoolSetting(soldeNegatif?.valeur)
  } catch (e) {
    settingsError.value = e.response?.data?.error || 'Impossible de charger les paramètres.'
  } finally {
    settingsLoading.value = false
  }
}

async function saveTelephoneDoublon(value) {
  if (!canEditSettings.value) return
  telephoneDoublon.value = value
  savingTelephoneDoublon.value = true
  try {
    await api.put(`/settings/${TELEPHONE_DOUBLON_KEY}`, { valeur: value })
  } catch (e) {
    settingsError.value = e.response?.data?.error || 'Impossible d\'enregistrer le paramètre.'
  } finally {
    savingTelephoneDoublon.value = false
  }
}

async function saveAutoriserSoldeNegatif(value) {
  if (!canEditSettings.value) return
  autoriserSoldeNegatif.value = value
  savingAutoriserSoldeNegatif.value = true
  try {
    await api.put(`/settings/${CLIENT_AUTORISER_SOLDE_NEGATIF_KEY}`, {
      valeur: value ? 'true' : 'false',
    })
  } catch (e) {
    autoriserSoldeNegatif.value = !value
    settingsError.value = e.response?.data?.error || 'Impossible d\'enregistrer le paramètre.'
  } finally {
    savingAutoriserSoldeNegatif.value = false
  }
}

function syncFromQuery() {
  const tab = route.query.tab
  if (tab === 'agency' && canViewSettings.value) {
    activeTab.value = 'agency'
  } else if (tab === 'impressions' && canViewSettings.value) {
    activeTab.value = 'impressions'
  } else if (tab === 'settings' && canViewSettings.value) {
    activeTab.value = 'settings'
  } else if (tab === 'referentiel' && canViewReferentiel.value) {
    activeTab.value = 'referentiel'
  } else if (tab === 'taux-historique' && canViewReferentiel.value) {
    activeTab.value = 'taux-historique'
  } else if (tab === 'corbeille' && canViewSettings.value) {
    activeTab.value = 'corbeille'
  } else {
    activeTab.value = 'appearance'
  }
}

watch(activeTab, (tab) => {
  router.replace({ query: { ...route.query, tab } })
  if (tab === 'settings') loadSettings()
  if (tab === 'taux-historique') tauxHistoriquePanel.value?.load()
  if (tab === 'corbeille') corbeillePanel.value?.load()
})

syncFromQuery()
if (activeTab.value === 'settings') loadSettings()
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
            <Tab v-if="canViewReferentiel" value="referentiel">Pays & Devises</Tab>
            <Tab v-if="canViewReferentiel" value="taux-historique">Historique des changements de taux</Tab>
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
              <Message v-if="settingsError" severity="error" class="settings-error" closable @close="settingsError = null">
                {{ settingsError }}
              </Message>
              <div class="settings-dedicated">
                <div class="field">
                  <label for="telephone-doublon">Doublon de téléphone (client / bénéficiaire)</label>
                  <Select
                    id="telephone-doublon"
                    :model-value="telephoneDoublon"
                    :options="telephoneDoublonOptions"
                    option-label="label"
                    option-value="value"
                    :disabled="!canEditSettings || savingTelephoneDoublon || settingsLoading"
                    fluid
                    @update:model-value="saveTelephoneDoublon"
                  />
                  <small class="settings-dedicated__hint">
                    Toujours bloquer : Refuse simplement l'enregistrement. <br>
                    Sélection automatique : Refuse l'enregistrement et sélectionne le client existant.
                  </small>
                </div>
                <div class="field">
                  <label for="autoriser-solde-negatif">Autoriser le solde négatif sur les opérations client</label>
                  <ToggleSwitch
                    id="autoriser-solde-negatif"
                    :model-value="autoriserSoldeNegatif"
                    :disabled="!canEditSettings || savingAutoriserSoldeNegatif || settingsLoading"
                    @update:model-value="saveAutoriserSoldeNegatif"
                  />
                  <small class="settings-dedicated__hint">
                    Désactivé : retrait, change et transfert depuis compte sont refusés si le solde est insuffisant.<br>
                    Activé : ces opérations peuvent mettre le solde client en négatif.
                  </small>
                </div>
                <NumerotationSettingsPanel :can-edit="canEditSettings" />
              </div>
            </TabPanel>

            <TabPanel v-if="canViewReferentiel" value="referentiel">
              <PaysDevisesPanel :can-edit="canEditReferentiel" />
            </TabPanel>

            <TabPanel v-if="canViewReferentiel" value="taux-historique">
              <TauxHistoriquePanel ref="tauxHistoriquePanel" />
            </TabPanel>

            <TabPanel v-if="canViewSettings" value="corbeille">
              <CorbeillePanel ref="corbeillePanel" />
            </TabPanel>
          </TabPanels>
        </Tabs>

        <Message
          v-if="!canViewSettings && !canViewReferentiel"
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
.configurations-page__intro {
  margin-bottom: 1rem;
}

.configurations-page__intro h2 {
  margin: 0 0 0.25rem;
  font-size: 1.125rem;
}

.configurations-page__intro p {
  margin: 0;
  color: var(--text-color-secondary, #64748b);
  font-size: 0.875rem;
}

.configurations-page__info {
  margin-top: 1rem;
}

.settings-error {
  margin-bottom: 1rem;
}

.settings-dedicated {
  margin-bottom: 1.25rem;
  max-width: 36rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.settings-dedicated .field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.settings-dedicated label {
  font-size: 0.875rem;
  font-weight: 600;
}

.settings-dedicated__hint {
  color: var(--text-color-secondary, #64748b);
  font-size: 0.8rem;
  line-height: 1.35;
}
</style>
