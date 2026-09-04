<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
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
      <h3>Clients</h3>
      <p class="corbeille-panel__hint">Clients masqués. La restauration les réaffiche dans le module Clients.</p>
      <AppTableState
        :loading="loadingClients"
        :error="errorClients"
        :is-empty="!loadingClients && !errorClients && clients.length === 0"
        empty-title="Aucun client dans la corbeille"
        empty-text="Les clients supprimés apparaîtront ici."
        @retry="loadClients"
      >
        <AppEntityDataView
          v-if="isAppMobile"
          :items="clients"
          :title-of="clientLabel"
          :code-of="(item) => item.code"
          :meta-of="(item) => `Supprimé le ${formatDate(item.updatedAt)}`"
          :actions-of="(item) => [{ label: 'Restaurer', icon: 'pi pi-replay', command: () => restoreClient(item) }]"
        />
        <DataTable v-else :value="clients" paginator :rows="10" striped-rows data-key="id">
          <Column header="Nom">
            <template #body="{ data }">
              {{ clientLabel(data) }}
            </template>
          </Column>
          <Column field="code" header="Code" />
          <Column header="Supprimé le">
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
    </section>
  </div>
</template>

<style scoped>
.corbeille-panel {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.corbeille-panel__section h3 {
  margin: 0 0 0.25rem;
  font-size: 1rem;
}

.corbeille-panel__hint {
  margin: 0 0 0.75rem;
  color: var(--text-color-secondary, #64748b);
  font-size: 0.8rem;
  line-height: 1.35;
}
</style>
