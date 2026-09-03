<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import ClientFormFields from '@/domains/client/components/ClientFormFields.vue'
import { getClientDetail, updateClient, deleteClient } from '@/domains/client/services/clientService'
import { listInvoices } from '@/domains/finance/services/invoiceService'
import { listProjects } from '@/domains/project/services/projectService'
import { formatDateFr, invoiceStatusLabel, invoiceStatusSeverity, projectStatusLabel } from '@/domains/shared/utils/entLabels'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'

const route = useRoute()
const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()

const client = ref(null)
const projects = ref([])
const invoices = ref([])
const loading = ref(true)
const error = ref(null)
const dialog = ref(false)
const form = ref({ title: '', description: '', code: '' })

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  return errs
})

async function load() {
  loading.value = true
  error.value = null
  try {
    client.value = await getClientDetail(route.params.id)
    const [allProjects, allInvoices] = await Promise.all([listProjects(), listInvoices()])
    projects.value = allProjects.filter((p) => p.clientId === client.value.id)
    invoices.value = allInvoices.filter((i) => i.clientId === client.value.id)
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le client.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openEdit() {
  form.value = {
    title: client.value.title ?? '',
    description: client.value.description ?? '',
    code: client.value.code,
  }
  resetErrors()
  dialog.value = true
}

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  try {
    await updateClient(client.value.id, {
      title: form.value.title.trim(),
      description: form.value.description || null,
    })
    dialog.value = false
    await load()
    toast.add({ severity: 'success', summary: 'Client', detail: 'Modifié.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Client', detail: e.response?.data?.error || 'Erreur.' })
  }
})

function askDelete() {
  confirm.require({
    header: 'Supprimer le client',
    message: `Supprimer « ${client.value.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async () => {
  try {
    await deleteClient(client.value.id)
    toast.add({ severity: 'success', summary: 'Client', detail: 'Supprimé.' })
    router.push({ name: 'clients' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Client', detail: e.response?.data?.error || 'Suppression impossible.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <div v-if="loading" class="dashboard-page__state">Chargement…</div>
    <div v-else-if="error" class="dashboard-page__state">{{ error }}</div>

    <template v-else-if="client">
      <Card class="dashboard-panel">
        <template #title>
          <div class="detail-header">
            <div>
              <span class="detail-header__code">{{ client.code }}</span>
              <h1 class="detail-header__title">{{ client.title }}</h1>
            </div>
            <div class="detail-header__actions">
              <Button
                v-can="'client.clients.update'"
                label="Modifier"
                icon="pi pi-pencil"
                outlined
                @click="openEdit"
              />
              <Button
                v-can="'client.clients.delete'"
                label="Supprimer"
                icon="pi pi-trash"
                severity="danger"
                outlined
                :loading="deleting"
                @click="askDelete"
              />
            </div>
          </div>
        </template>
        <template #content>
          <TabView>
            <TabPanel header="Informations">
              <dl class="detail-dl">
                <div><dt>Code</dt><dd>{{ client.code }}</dd></div>
                <div><dt>Titre</dt><dd>{{ client.title }}</dd></div>
                <div><dt>Description</dt><dd>{{ client.description || '—' }}</dd></div>
                <div><dt>Statut</dt><dd><Tag :value="client.isEnabled ? 'Actif' : 'Inactif'" /></dd></div>
                <div><dt>Créé le</dt><dd>{{ formatDateFr(client.createdAt) }}</dd></div>
              </dl>
            </TabPanel>
            <TabPanel :header="`Projets (${client.projectCount ?? projects.length})`">
              <DataTable v-if="projects.length" :value="projects" striped-rows>
                <Column field="code" header="Code" />
                <Column field="title" header="Titre" />
                <Column header="Statut">
                  <template #body="{ data }">{{ projectStatusLabel(data.status) }}</template>
                </Column>
                <Column header="">
                  <template #body="{ data }">
                    <Button icon="pi pi-eye" text rounded @click="router.push({ name: 'project-detail', params: { id: data.id } })" />
                  </template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">Aucun projet associé.</p>
            </TabPanel>
            <TabPanel :header="`Factures (${client.invoiceCount ?? invoices.length})`">
              <DataTable v-if="invoices.length" :value="invoices" striped-rows>
                <Column field="number" header="N°" />
                <Column header="Date">
                  <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
                </Column>
                <Column header="Montant">
                  <template #body="{ data }">{{ formatMontant(data.amount, DEVISE_APP) }}</template>
                </Column>
                <Column header="Statut">
                  <template #body="{ data }">
                    <Tag :value="invoiceStatusLabel(data.status)" :severity="invoiceStatusSeverity(data.status)" />
                  </template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">Aucune facture.</p>
            </TabPanel>
          </TabView>
        </template>
      </Card>
    </template>

    <Dialog v-model:visible="dialog" header="Modifier client" modal style="width: min(640px, 95vw)">
      <ClientFormFields v-model="form" :errors="fieldErrors" show-code />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.detail-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  width: 100%;
}

.detail-header__code {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--layout-text-muted);
  text-transform: uppercase;
}

.detail-header__title {
  margin: 0.25rem 0 0;
  font-size: 1.25rem;
}

.detail-header__actions {
  display: flex;
  gap: 0.5rem;
}

.detail-dl {
  display: grid;
  gap: 0.75rem;
  margin: 0;
}

.detail-dl div {
  display: grid;
  grid-template-columns: 8rem 1fr;
  gap: 0.5rem;
}

.detail-dl dt {
  font-weight: 600;
  color: var(--layout-text-muted);
}

.detail-dl dd {
  margin: 0;
}

.dashboard-page__state {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
