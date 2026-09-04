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
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppFilterSelect from '@/domains/shared/components/AppFilterSelect.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const toast = useAppToast()
const { isAppMobile } = useAppMobileLayout()
const items = ref([])
const users = ref([])
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const searchTerm = ref('')
const rowContextMenu = ref()

const first = ref(0)
const totalRecords = ref(0)

const filterAction = ref(null)
const filterUserId = ref(null)
const filterPeriod = ref(null)
const knownActions = ref(new Set())

const AUDIT_COLUMNS = [
  { key: 'date_action', label: 'Date', defaultVisible: true },
  { key: 'action', label: 'Action', defaultVisible: true },
  { key: 'utilisateur', label: 'Utilisateur', defaultVisible: true },
  { key: 'description', label: 'Description', defaultVisible: true },
]

const {
  ROW_OPTIONS,
  columns: tableColumns,
  visibleColKeys,
  rows: tableRows,
  showIndex,
  sortField,
  sortOrder,
  sortOptions,
  isColVisible,
  toggleCol,
} = useTableSettings('table_audit_logs', AUDIT_COLUMNS, {
  defaultRows: 20,
  defaultSortField: 'date_action',
  defaultSortOrder: -1,
})

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
    page: Math.floor(first.value / tableRows.value) + 1,
    limit: tableRows.value,
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

watch(tableRows, (val, oldVal) => {
  if (val === oldVal) return
  first.value = 0
  load()
})

function onPage(event) {
  first.value = event.first
  if (event.rows !== tableRows.value) {
    tableRows.value = event.rows
    return
  }
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

const displayItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [
        formatActionLabel(item.action),
        item.description,
        userLabel(item.utilisateur_id),
        userDisplayName(item.utilisateur_id),
        formatDateTime(item.date_action),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(q),
    )
  }
  const enriched = list.map((item) => ({
    ...item,
    _utilisateur: userLabel(item.utilisateur_id),
    _actionLabel: formatActionLabel(item.action),
  }))
  const fieldMap = { utilisateur: '_utilisateur', action: '_actionLabel' }
  const field = fieldMap[sortField.value] || sortField.value
  return sortByField(enriched, field, sortOrder.value)
})

const countLabel = computed(() => `${totalRecords.value}`)

function resetFilters() {
  filterAction.value = null
  filterUserId.value = null
  filterPeriod.value = null
}

function buildMenuItems(item) {
  return [
    {
      label: 'Copier la description',
      icon: 'pi pi-copy',
      command: async () => {
        const text = item.description || ''
        try {
          await navigator.clipboard.writeText(text)
          toast.add({ severity: 'success', summary: 'Audit', detail: 'Description copiée.' })
        } catch {
          toast.add({ severity: 'warn', summary: 'Audit', detail: 'Copie impossible.' })
        }
      },
    },
  ]
}

function onRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
}
</script>

<template>
  <section class="dashboard-page audit-log">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Journal d'audit"
          :count-label="countLabel"
          :show-create="false"
          :sticky="isAppMobile"
          :reloading="reloading"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher dans la page…"
          @reload="reload"
        >
          <template #actions>
            <AppTableSettingsPopover
              v-model:visible-col-keys="visibleColKeys"
              v-model:rows="tableRows"
              v-model:show-index="showIndex"
              v-model:sort-field="sortField"
              v-model:sort-order="sortOrder"
              :columns="tableColumns"
              :row-options="ROW_OPTIONS"
              :sort-options="sortOptions"
              @toggle-col="toggleCol"
            >
              <template #filters>
                <p class="app-table-settings__title">Filtres</p>
                <DatePicker
                  v-model="filterPeriod"
                  selection-mode="range"
                  date-format="dd/mm/yy"
                  show-icon
                  icon-display="button"
                  show-clear
                  placeholder="Période"
                  fluid
                  size="small"
                  class="app-table-settings__mb"
                />
                <AppFilterSelect
                  v-model="filterAction"
                  :options="actionOptions"
                  option-label="label"
                  option-value="value"
                  placeholder="Action"
                  show-clear
                  fluid
                  size="small"
                  class="app-table-settings__mb"
                />
                <AppFilterSelect
                  v-model="filterUserId"
                  :options="userOptions"
                  option-label="label"
                  option-value="value"
                  placeholder="Utilisateur"
                  show-clear
                  fluid
                  size="small"
                  class="app-table-settings__mb"
                />
                <Button
                  v-if="activeFilterCount > 0"
                  label="Réinitialiser"
                  icon="pi pi-filter-slash"
                  severity="secondary"
                  outlined
                  size="small"
                  fluid
                  @click="resetFilters"
                />
              </template>
            </AppTableSettingsPopover>
          </template>
        </AppTablePanelHeader>
      </template>
      <template #content>
        <AppTableState
          :loading="loading"
          :error="error"
          :is-empty="!loading && !error && displayItems.length === 0"
          empty-icon="pi pi-history"
          empty-title="Aucune entrée"
          :empty-text="activeFilterCount > 0 || searchTerm.trim() ? 'Aucune action ne correspond aux filtres sélectionnés.' : 'Aucune action n\'a encore été enregistrée.'"
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

          <AppEntityDataView
            v-if="isAppMobile"
            :items="displayItems"
            :title-of="(item) => formatActionLabel(item.action)"
            :subtitle-of="(item) => item.description || null"
            :meta-of="(item) => `${formatDateTime(item.date_action)} · ${userLabel(item.utilisateur_id)}`"
            :status-of="(item) => ({ value: formatActionLabel(item.action), severity: actionSeverity(item.action) })"
            :actions-of="buildMenuItems"
            :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
            data-key="id"
          />
          <DataTable
            v-else
            :value="displayItems"
            lazy
            paginator
            :rows="tableRows"
            :first="first"
            :total-records="totalRecords"
            :rows-per-page-options="ROW_OPTIONS"
            striped-rows
            :sort-field="sortField === 'utilisateur' || sortField === 'action' ? undefined : (sortField || undefined)"
            :sort-order="sortOrder"
            @page="onPage"
            @row-contextmenu="onRowContextMenu"
          >
            <Column v-if="showIndex" header="#" style="width: 3.5rem">
              <template #body="{ index }">{{ first + index + 1 }}</template>
            </Column>
            <Column v-if="isColVisible('date_action')" field="date_action" header="Date" style="width: 10.5rem" sortable>
              <template #body="{ data }">
                <div class="audit-log__date-cell">
                  <span class="audit-log__date">{{ formatDateTime(data.date_action) }}</span>
                </div>
              </template>
            </Column>
            <Column v-if="isColVisible('action')" field="action" header="Action" style="width: 12rem" sortable>
              <template #body="{ data }">
                <Tag
                  class="audit-log__action-tag"
                  :value="formatActionLabel(data.action)"
                  :severity="actionSeverity(data.action)"
                  :title="data.action"
                />
              </template>
            </Column>
            <Column v-if="isColVisible('utilisateur')" header="Utilisateur" style="width: 10rem">
              <template #body="{ data }">
                <div class="audit-log__user-cell">
                  <span class="audit-log__user-login">{{ userLabel(data.utilisateur_id) }}</span>
                  <span class="audit-log__user-name">{{ userDisplayName(data.utilisateur_id) }}</span>
                </div>
              </template>
            </Column>
            <Column v-if="isColVisible('description')" field="description" header="Description" sortable>
              <template #body="{ data }">
                <p class="audit-log__description" :title="data.description">
                  {{ data.description || '—' }}
                </p>
              </template>
            </Column>
          </DataTable>
          <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
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
</style>
