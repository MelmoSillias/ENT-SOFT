<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import AppStatsCards from '@/domains/shared/components/AppStatsCards.vue'
import DashboardSkeleton from '@/domains/shared/components/DashboardSkeleton.vue'
import { getDashboardSummary } from '@/domains/dashboard/services/dashboardService'
import { useAuthStore } from '@/domains/auth/stores/auth'
import { appConfig } from '@/config/app'

const router = useRouter()
const authStore = useAuthStore()
const brand = appConfig.branding

const summary = ref(null)
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    summary.value = await getDashboardSummary()
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le tableau de bord.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

const firstName = computed(() => {
  const prenom = authStore.user?.prenom?.trim()
  if (prenom) return prenom
  return authStore.user?.login || null
})

const greeting = computed(() => {
  const hour = new Date().getHours()
  let salutation = 'Bonjour'
  if (hour >= 18 || hour < 5) salutation = 'Bonsoir'
  else if (hour >= 12) salutation = 'Bon après-midi'
  return firstName.value ? `${salutation}, ${firstName.value}` : salutation
})

const todayLabel = computed(() => {
  try {
    return new Intl.DateTimeFormat('fr-FR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    }).format(new Date())
  } catch {
    return ''
  }
})

const statsItems = computed(() => {
  if (!summary.value) return []
  return [
    {
      label: 'Projets actifs',
      value: String(summary.value.activeProjects ?? 0),
      hint: 'Projets en cours',
      icon: 'pi pi-briefcase',
    },
    {
      label: 'Tâches du jour',
      value: String(summary.value.tasksToday ?? 0),
      hint: 'Échéances aujourd\'hui',
      icon: 'pi pi-calendar',
    },
    {
      label: 'Clients',
      value: String(summary.value.clients ?? 0),
      hint: 'Clients actifs',
      icon: 'pi pi-users',
    },
    {
      label: 'Factures impayées',
      value: String(summary.value.unpaidInvoices ?? 0),
      hint: 'Facturées avec solde restant',
      icon: 'pi pi-file',
      variant: 'amount',
    },
  ]
})

const quickActions = computed(() => {
  const actions = []
  if (authStore.hasModuleAccess('project')) {
    actions.push({ label: 'Projets', icon: 'pi pi-briefcase', route: 'projects' })
  }
  if (authStore.hasModuleAccess('task')) {
    actions.push({ label: 'Planning', icon: 'pi pi-calendar', route: 'tasks' })
  }
  if (authStore.hasModuleAccess('client')) {
    actions.push({ label: 'Clients', icon: 'pi pi-users', route: 'clients' })
  }
  if (authStore.hasModuleAccess('stock')) {
    actions.push({ label: 'Matériels', icon: 'pi pi-box', route: 'equipments' })
  }
  if (authStore.hasModuleAccess('finance')) {
    actions.push({ label: 'Finances', icon: 'pi pi-wallet', route: 'finances' })
  }
  return actions
})

function goTo(routeName) {
  router.push({ name: routeName })
}
</script>

<template>
  <section class="dashboard-page dashboard-home">
    <header class="dashboard-home__intro">
      <div class="dashboard-home__intro-main">
        <div class="dashboard-home__intro-icon" aria-hidden="true">
          <i class="pi pi-home" />
        </div>
        <div class="dashboard-home__intro-copy">
          <p v-if="todayLabel" class="dashboard-home__eyebrow">{{ todayLabel }}</p>
          <h1 class="dashboard-home__title">{{ greeting }}</h1>
          <p class="dashboard-home__subtitle">Vue d'ensemble de l'activité</p>
        </div>
      </div> 
    </header>

    <DashboardSkeleton v-if="loading" :show-exchange-rates="false" />

    <div v-else-if="error" class="dashboard-page__state">
      <p>{{ error }}</p>
      <Button label="Réessayer" icon="pi pi-refresh" text @click="load" />
    </div>

    <template v-else-if="summary">
      <AppStatsCards :items="statsItems" />

      <Card v-if="quickActions.length" class="dashboard-panel dashboard-panel--compact dashboard-panel--sober">
        <template #title>
          <span class="dashboard-panel__title">Actions rapides</span>
        </template>
        <template #content>
          <div class="dashboard-quick-actions">
            <Button
              v-for="action in quickActions"
              :key="action.route"
              :label="action.label"
              :icon="action.icon"
              outlined
              @click="goTo(action.route)"
            />
          </div>
        </template>
      </Card>
    </template>
  </section>
</template>

<style scoped>
.dashboard-page__state {
  padding: 2rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: var(--layout-text-muted);
}

.dashboard-quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>
