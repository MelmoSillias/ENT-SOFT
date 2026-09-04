<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

const { isAppMobile } = useAppMobileLayout()
const items = ref([])
const loading = ref(true)
const searchTerm = ref('')

const COLUMNS = [
  { key: 'canal', label: 'Canal', defaultVisible: true },
  { key: 'destinataire', label: 'Destinataire', defaultVisible: true },
  { key: 'statut', label: 'Statut', defaultVisible: true },
  { key: 'tentatives', label: 'Tentatives', defaultVisible: true },
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
} = useTableSettings('table_notifications', COLUMNS, { defaultSortField: 'canal' })

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter(
      (n) =>
        String(n.canal || '').toLowerCase().includes(q) ||
        String(n.destinataire || '').toLowerCase().includes(q) ||
        String(n.statut || '').toLowerCase().includes(q),
    )
  }
  return sortByField(list, sortField.value, sortOrder.value)
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/notifications')
    items.value = data.items ?? data
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #content>
        <AppTablePanelHeader
          title="Notifications"
          :count-label="`${filteredItems.length}`"
          :show-create="false"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher…"
          :sticky="isAppMobile"
          :reloading="loading"
          @reload="load"
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
        <DataTable
          :value="filteredItems"
          paginator
          :rows="tableRows"
          striped-rows
          :sort-field="sortField || undefined"
          :sort-order="sortOrder"
        >
          <Column v-if="showIndex" header="#" style="width: 3.5rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column v-if="isColVisible('canal')" field="canal" header="Canal" sortable />
          <Column v-if="isColVisible('destinataire')" field="destinataire" header="Destinataire" sortable />
          <Column v-if="isColVisible('statut')" field="statut" header="Statut" sortable>
            <template #body="{ data }"><Tag :value="data.statut" /></template>
          </Column>
          <Column v-if="isColVisible('tentatives')" field="tentatives" header="Tentatives" sortable />
        </DataTable>
      </template>
    </Card>
  </section>
</template>
