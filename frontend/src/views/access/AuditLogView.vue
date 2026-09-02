<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DatePicker from 'primevue/datepicker'
import Tag from 'primevue/tag'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppFiltersCard from '@/domains/shared/components/AppFiltersCard.vue'
import AppFilterSelect from '@/domains/shared/components/AppFilterSelect.vue'

const items = ref([])
const users = ref([])
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)

const first = ref(0)
const rows = ref(20)
const totalRecords = ref(0)

const filterAction = ref(null)
const filterUserId = ref(null)
const filterPeriod = ref(null)
const knownActions = ref(new Set())

async function loadUsers() {
  try {
    const { data } = await api.get('/users')
    users.value = Array.isArray(data) ? data : (data.items ?? [])
  } catch {
    users.value = []
  }
}

function buildParams() {
  const params = {
    page: Math.floor(first.value / rows.value) + 1,
    limit: rows.value,
  }
  if (filterAction.value) params.action = filterAction.value
  if (filterUserId.value) params.utilisateur_id = filterUserId.value
  if (filterPeriod.value?.[0]) params.from = startOfDay(filterPeriod.value[0]).toISOString()
  if (filterPeriod.value?.[1]) params.to = endOfDay(filterPeriod.value[1]).toISOString()
  return params
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/audit-logs', { params: buildParams() })
    items.value = Array.isArray(data) ? data : (data.data ?? data.items ?? [])
    totalRecords.value = data.meta?.total ?? items.value.length
    for (const item of items.value) {
      if (item.action) knownActions.value.add(item.action)
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le journal d\'audit.'
    items.value = []
    totalRecords.value = 0
  } finally {
    loading.value = false
  }
}

async function reload() {
  reloading.value = true
  try {
    await load()
  } finally {
    reloading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadUsers(), load()])
})

watch([filterAction, filterUserId, filterPeriod], () => {
  first.value = 0
  load()
})

function onPage(event) {
  first.value = event.first
  rows.value = event.rows
  load()
}

function startOfDay(date) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  return d
}

function endOfDay(date) {
  const d = new Date(date)
  d.setHours(23, 59, 59, 999)
  return d
}

function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatActionLabel(action) {
  if (!action) return '—'
  return action
    .toLowerCase()
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function actionSeverity(action) {
  if (!action) return 'secondary'
  const value = action.toUpperCase()
  if (/CREATE|CONFIRME|DELIVRE|AJOUT/.test(value)) return 'success'
  if (/ANNULE|DELETE|SUPPR|SUSPEND|RETIR/.test(value)) return 'danger'
  if (/MODIF|UPDATE|CHANGE|EDIT/.test(value)) return 'warn'
  if (/LOGIN|AUTH|CONNEX/.test(value)) return 'info'
  return 'secondary'
}

function userLabel(userId) {
  const user = users.value.find((u) => u.id === userId)
  if (user) return user.login
  return 'Utilisateur inconnu'
}

function userDisplayName(userId) {
  const user = users.value.find((u) => u.id === userId)
  if (!user) return 'Utilisateur inconnu'
  return [user.prenom, user.nom].filter(Boolean).join(' ') || user.login
}

const actionOptions = computed(() =>
  [...knownActions.value]
    .sort((a, b) => a.localeCompare(b, 'fr'))
    .map((action) => ({ label: formatActionLabel(action), value: action })),
)

const userOptions = computed(() =>
  [...users.value]
    .map((user) => ({
      label: user.login,
      value: user.id,
    }))
    .sort((a, b) => a.label.localeCompare(b.label, 'fr')),
)

const activeFilterCount = computed(() => {
  let count = 0
  if (filterAction.value) count++
  if (filterUserId.value) count++
  if (filterPeriod.value) count++
  return count
})

const countLabel = computed(() => {
  if (activeFilterCount.value > 0) {
    return `${totalRecords.value}`
  }
  return `${totalRecords.value}`
})

function resetFilters() {
  filterAction.value = null
  filterUserId.value = null
  filterPeriod.value = null
}
</script>

<template>
  <section class="dashboard-page audit-log">
    <AppFiltersCard :active-count="activeFilterCount" class="audit-log__filters">
      <div class="filter-field filter-field--period">
        <label class="filter-label" for="audit-filter-period">Période</label>
        <DatePicker
          id="audit-filter-period"
          v-model="filterPeriod"
          selection-mode="range"
          date-format="dd/mm/yy"
          show-icon
          icon-display="button"
          show-clear
          placeholder="Toutes les dates"
          fluid
        />
      </div>
      <div class="filter-field">
        <label class="filter-label" for="audit-filter-action">Action</label>
        <AppFilterSelect
          id="audit-filter-action"
          v-model="filterAction"
          :options="actionOptions"
          option-label="label"
          option-value="value"
          placeholder="Toutes les actions"
          show-clear
          fluid
        />
      </div>
      <div class="filter-field">
        <label class="filter-label" for="audit-filter-user">Utilisateur</label>
        <AppFilterSelect
          id="audit-filter-user"
          v-model="filterUserId"
          :options="userOptions"
          option-label="label"
          option-value="value"
          placeholder="Tous les utilisateurs"
          show-clear
          fluid
        />
      </div>
      <div class="filter-field filter-field--actions">
        <Button
          icon="pi pi-filter-slash"
          severity="secondary"
          text
          rounded
          aria-label="Réinitialiser les filtres"
          v-tooltip.top="'Réinitialiser'"
          :disabled="activeFilterCount === 0"
          @click="resetFilters"
        />
      </div>
    </AppFiltersCard>

    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Journal d'audit"
          :count-label="countLabel"
          :show-create="false"
          :reloading="reloading"
          @reload="reload"
        />
      </template>
      <template #content>
        <AppTableState
          :loading="loading"
          :error="error"
          :is-empty="!loading && !error && items.length === 0"
          empty-icon="pi pi-history"
          empty-title="Aucune entrée"
          :empty-text="activeFilterCount > 0 ? 'Aucune action ne correspond aux filtres sélectionnés.' : 'Aucune action n\'a encore été enregistrée.'"
          @retry="load"
        >
          <template #empty-action>
            <Button
              v-if="activeFilterCount > 0"
              label="Effacer les filtres"
              icon="pi pi-filter-slash"
              class="mt-3"
              severity="secondary"
              outlined
              @click="resetFilters"
            />
          </template>

          <DataTable
            :value="items"
            lazy
            paginator
            :rows="rows"
            :first="first"
            :total-records="totalRecords"
            :rows-per-page-options="[10, 20, 50]"
            striped-rows
            @page="onPage"
          >
            <Column header="Date" style="width: 10.5rem">
              <template #body="{ data }">
                <div class="audit-log__date-cell">
                  <span class="audit-log__date">{{ formatDateTime(data.date_action) }}</span>
                </div>
              </template>
            </Column>
            <Column header="Action" style="width: 12rem">
              <template #body="{ data }">
                <Tag
                  class="audit-log__action-tag"
                  :value="formatActionLabel(data.action)"
                  :severity="actionSeverity(data.action)"
                  :title="data.action"
                />
              </template>
            </Column>
            <Column header="Utilisateur" style="width: 10rem">
              <template #body="{ data }">
                <div class="audit-log__user-cell">
                  <span class="audit-log__user-login">{{ userLabel(data.utilisateur_id) }}</span>
                  <span class="audit-log__user-name">{{ userDisplayName(data.utilisateur_id) }}</span>
                </div>
              </template>
            </Column>
            <Column header="Description">
              <template #body="{ data }">
                <p class="audit-log__description" :title="data.description">
                  {{ data.description || '—' }}
                </p>
              </template>
            </Column>
          </DataTable>
        </AppTableState>
      </template>
    </Card>
  </section>
</template>

<style scoped>
.audit-log {
  display: grid;
  gap: 0.75rem;
}

.audit-log__filters {
  margin-bottom: 0;
}

.audit-log__filters :deep(.app-filters-card__grid) {
  display: flex;
  flex-wrap: nowrap;
  align-items: flex-end;
  gap: 0.65rem;
  overflow-x: auto;
  padding-bottom: 0.15rem;
  scrollbar-width: thin;
}

.audit-log__filters :deep(.filter-field) {
  flex: 1 1 0;
  min-width: 8rem;
}

.audit-log__filters :deep(.filter-field--period) {
  flex: 1.2 1 0;
  min-width: 11rem;
}

.audit-log__filters :deep(.filter-field--actions) {
  flex: 0 0 auto;
  min-width: auto;
}

.audit-log__date-cell {
  display: grid;
  gap: 0.1rem;
}

.audit-log__date {
  font-size: 0.85rem;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.audit-log__action-tag {
  max-width: 100%;
  font-size: 0.78rem;
}

.audit-log__action-tag :deep(.p-tag-label) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.audit-log__user-cell {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.audit-log__user-login {
  font-size: 0.85rem;
  font-weight: 600;
}

.audit-log__user-name {
  font-size: 0.78rem;
  color: var(--layout-text-muted, var(--p-text-muted-color));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.audit-log__description {
  margin: 0;
  font-size: 0.85rem;
  line-height: 1.45;
  color: var(--layout-text, var(--p-text-color));
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@media (max-width: 767px) {
  .audit-log__filters :deep(.app-filters-card__grid) {
    flex-wrap: wrap;
    overflow-x: visible;
  }

  .audit-log__filters :deep(.filter-field) {
    flex: 1 1 100%;
    min-width: 0;
  }
}
</style>
