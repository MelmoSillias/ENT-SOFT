<script setup>
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import {
  listCorbeilleClients,
  restoreCorbeilleClient,
} from '@/domains/configuration/services/corbeilleService'

const toast = useAppToast()
const { isAppMobile } = useAppMobileLayout()
const clients = ref([])
const loadingClients = ref(false)
const errorClients = ref(null)
const searchTerm = ref('')
const rowContextMenu = ref()

const COLUMNS = [
  { key: 'title', label: 'Nom', defaultVisible: true },
  { key: 'code', label: 'Code', defaultVisible: true },
  { key: 'updatedAt', label: 'Supprimé le', defaultVisible: true },
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
} = useTableSettings('table_corbeille_clients', COLUMNS, { defaultSortField: 'updatedAt', defaultSortOrder: -1 })

function clientLabel(client) {
  return client.title || client.code || '—'
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const filteredClients = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = clients.value
  if (q) {
    list = list.filter(
      (c) =>
        String(c.title || '').toLowerCase().includes(q) ||
        String(c.code || '').toLowerCase().includes(q),
    )
  }
  return sortByField(list, sortField.value === 'title' ? 'title' : sortField.value, sortOrder.value)
})

function clientActions(item) {
  return [{ label: 'Restaurer', icon: 'pi pi-replay', command: () => restoreClient(item) }]
}

function onRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
}

async function loadClients() {
  loadingClients.value = true
  errorClients.value = null
  try {
    clients.value = await listCorbeilleClients()
  } catch (e) {
    errorClients.value = e.response?.data?.error || 'Impossible de charger les clients supprimés.'
  } finally {
    loadingClients.value = false
  }
}

async function load() {
  await loadClients()
}

const { pending: restoringClient, run: restoreClient } = useAsyncAction(async (client) => {
  try {
    await restoreCorbeilleClient(client.id)
    toast.add({ severity: 'success', summary: 'Corbeille', detail: `${clientLabel(client)} restauré.` })
    await loadClients()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Corbeille',
      detail: e.response?.data?.error || 'Restauration impossible.',
    })
  }
})

onMounted(load)

defineExpose({ load })
</script>

<template>
  <div class="corbeille-panel">
    <section class="corbeille-panel__section">
      <AppTablePanelHeader
        title="Clients"
        :count-label="`${filteredClients.length}`"
        :show-create="false"
        show-search
        v-model:search-term="searchTerm"
        search-placeholder="Rechercher…"
        :sticky="isAppMobile"
        @reload="loadClients"
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
          />
        </template>
      </AppTablePanelHeader>
      <p class="corbeille-panel__hint">Clients masqués. La restauration les réaffiche dans le module Clients.</p>
      <AppTableState
        :loading="loadingClients"
        :error="errorClients"
        :is-empty="!loadingClients && !errorClients && filteredClients.length === 0"
        empty-title="Aucun client dans la corbeille"
        empty-text="Les clients supprimés apparaîtront ici."
        @retry="loadClients"
      >
        <AppEntityDataView
          v-if="isAppMobile"
          :items="filteredClients"
          :title-of="clientLabel"
          :code-of="(item) => item.code"
          :meta-of="(item) => `Supprimé le ${formatDate(item.updatedAt)}`"
          :actions-of="clientActions"
          :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
        />
        <DataTable
          v-else
          :value="filteredClients"
          paginator
          :rows="tableRows"
          striped-rows
          data-key="id"
          :sort-field="sortField || undefined"
          :sort-order="sortOrder"
          @row-contextmenu="onRowContextMenu"
        >
          <Column v-if="showIndex" header="#" style="width: 3.5rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column v-if="isColVisible('title')" header="Nom" field="title" sortable>
            <template #body="{ data }">
              {{ clientLabel(data) }}
            </template>
          </Column>
          <Column v-if="isColVisible('code')" field="code" header="Code" sortable />
          <Column v-if="isColVisible('updatedAt')" header="Supprimé le" field="updatedAt" sortable>
            <template #body="{ data }">
              {{ formatDate(data.updatedAt) }}
            </template>
          </Column>
          <Column header="" style="width: 8rem">
            <template #body="{ data }">
              <Button
                label="Restaurer"
                icon="pi pi-replay"
                size="small"
                text
                :loading="restoringClient"
                @click="restoreClient(data)"
              />
            </template>
          </Column>
        </DataTable>
      </AppTableState>
      <AppRowContextMenu ref="rowContextMenu" :actions-of="clientActions" />
    </section>
  </div>
</template>

<style scoped>
.corbeille-panel {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.corbeille-panel__hint {
  margin: 0 0 0.75rem;
  color: var(--text-color-secondary, #64748b);
  font-size: 0.8rem;
  line-height: 1.35;
}
</style>
