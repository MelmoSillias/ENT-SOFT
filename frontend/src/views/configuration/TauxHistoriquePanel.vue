<script setup>
import { onMounted, ref } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import DeviseBadge from '@/domains/shared/components/DeviseBadge.vue'
import CountryFlag from '@/domains/shared/components/CountryFlag.vue'
import api from '@/services/api'

const rows = ref([])
const users = ref([])
const loading = ref(false)
const error = ref(null)

function normalizeList(data) {
  return Array.isArray(data) ? data : (data.items ?? [])
}

function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

function formatTaux(value) {
  if (value === null || value === undefined || value === '') return '—'
  const num = Number(value)
  if (Number.isNaN(num)) return String(value)
  return num.toLocaleString('fr-FR', { maximumFractionDigits: 6 })
}

function userLabel(userId) {
  if (!userId) return '—'
  const user = users.value.find((u) => u.id === userId)
  if (!user) return '—'
  return user.login || [user.prenom, user.nom].filter(Boolean).join(' ') || '—'
}

async function loadUsers() {
  try {
    const { data } = await api.get('/users')
    users.value = normalizeList(data)
  } catch {
    users.value = []
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/pays-devise-liaisons/historique')
    rows.value = normalizeList(data)
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger l\'historique des taux.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadUsers(), load()])
})

defineExpose({ load })
</script>

<template>
  <div class="taux-historique-panel">
    <AppTableState
      :loading="loading"
      :error="error"
      :is-empty="!loading && !error && rows.length === 0"
      empty-title="Aucun changement de taux"
      empty-text="Les modifications de taux des liaisons pays → devise apparaîtront ici."
      @retry="load"
    >
      <DataTable :value="rows" paginator :rows="15" striped-rows data-key="id">
        <Column header="Date" style="min-width: 9rem">
          <template #body="{ data }">
            {{ formatDateTime(data.date_modification) }}
          </template>
        </Column>

        <Column header="Pays" style="min-width: 12rem">
          <template #body="{ data }">
            <div class="pays-cell">
              <CountryFlag :code="data.pays_code" :size="20" />
              <span>{{ data.pays_nom }} ({{ data.pays_code }})</span>
            </div>
          </template>
        </Column>

        <Column header="Devise" style="min-width: 8rem">
          <template #body="{ data }">
            <DeviseBadge :code="data.devise_code" :symbole="data.devise_symbole" />
          </template>
        </Column>

        <Column header="Ancien taux" style="min-width: 8rem">
          <template #body="{ data }">
            {{ formatTaux(data.ancien_taux) }}
          </template>
        </Column>

        <Column header="Nouveau taux" style="min-width: 8rem">
          <template #body="{ data }">
            {{ formatTaux(data.nouveau_taux) }}
          </template>
        </Column>

        <Column header="Utilisateur" style="min-width: 12rem">
          <template #body="{ data }">
            {{ userLabel(data.utilisateur_id) }}
          </template>
        </Column>

        <Column field="motif" header="Motif" style="min-width: 10rem">
          <template #body="{ data }">
            {{ data.motif || '—' }}
          </template>
        </Column>
      </DataTable>
    </AppTableState>
  </div>
</template>

<style scoped>
.pays-cell {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
</style>
