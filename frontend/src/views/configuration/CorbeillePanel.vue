<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import {
  listCorbeilleClients,
  listCorbeilleComptes,
  restoreCorbeilleClient,
  restoreCorbeilleCompte,
} from '@/domains/configuration/services/corbeilleService'

const toast = useAppToast()
const comptes = ref([])
const clients = ref([])
const loadingComptes = ref(false)
const loadingClients = ref(false)
const errorComptes = ref(null)
const errorClients = ref(null)

function clientLabel(client) {
  return [client.prenom, client.nom].filter(Boolean).join(' ') || client.nom || '—'
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

async function loadComptes() {
  loadingComptes.value = true
  errorComptes.value = null
  try {
    comptes.value = await listCorbeilleComptes()
  } catch (e) {
    errorComptes.value = e.response?.data?.error || 'Impossible de charger les comptes supprimés.'
  } finally {
    loadingComptes.value = false
  }
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
  await Promise.all([loadComptes(), loadClients()])
}

const { pending: restoringCompte, run: restoreCompte } = useAsyncAction(async (compte) => {
  try {
    await restoreCorbeilleCompte(compte.id)
    toast.add({ severity: 'success', summary: 'Corbeille', detail: `Compte ${compte.numeroCompte} restauré.` })
    await loadComptes()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Corbeille',
      detail: e.response?.data?.error || 'Restauration impossible.',
    })
  }
})

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
      <h3>Comptes agence</h3>
      <p class="corbeille-panel__hint">Comptes masqués. La restauration réaffiche le compte et ses transactions.</p>
      <AppTableState
        :loading="loadingComptes"
        :error="errorComptes"
        :is-empty="!loadingComptes && !errorComptes && comptes.length === 0"
        empty-title="Aucun compte dans la corbeille"
        empty-text="Les comptes agence supprimés apparaîtront ici."
        @retry="loadComptes"
      >
        <DataTable :value="comptes" paginator :rows="10" striped-rows data-key="id">
          <Column field="numeroCompte" header="Numéro" />
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
                :loading="restoringCompte"
                @click="restoreCompte(data)"
              />
            </template>
          </Column>
        </DataTable>
      </AppTableState>
    </section>

    <section class="corbeille-panel__section">
      <h3>Clients</h3>
      <p class="corbeille-panel__hint">Clients masqués. La restauration réaffiche le client, son compte et ses opérations.</p>
      <AppTableState
        :loading="loadingClients"
        :error="errorClients"
        :is-empty="!loadingClients && !errorClients && clients.length === 0"
        empty-title="Aucun client dans la corbeille"
        empty-text="Les clients supprimés apparaîtront ici."
        @retry="loadClients"
      >
        <DataTable :value="clients" paginator :rows="10" striped-rows data-key="id">
          <Column header="Nom">
            <template #body="{ data }">
              {{ clientLabel(data) }}
            </template>
          </Column>
          <Column field="telephone" header="Téléphone" />
          <Column header="Compte">
            <template #body="{ data }">
              {{ data.numeroCompte || '—' }}
            </template>
          </Column>
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
