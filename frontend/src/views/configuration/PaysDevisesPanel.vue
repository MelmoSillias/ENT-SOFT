<script setup>
import { computed, onMounted, ref } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import DeviseBadge from '@/domains/shared/components/DeviseBadge.vue'
import CountryFlag from '@/domains/shared/components/CountryFlag.vue'
import api from '@/services/api'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'

const props = defineProps({
  canEdit: { type: Boolean, default: false },
})

const confirm = useConfirm()

const pays = ref([])
const devises = ref([])
const rows = ref([])
const draftRows = ref([])
const deviseLocale = ref('XOF')
const loading = ref(true)
const error = ref(null)
const rowErrors = ref({})
const savingRowKey = ref(null)
const togglingRowKey = ref(null)
const deletingRowKey = ref(null)

const deviseLocaleOptions = computed(() =>
  devises.value.map((d) => ({ label: `${d.code} — ${d.nom}`, value: d.code })),
)

const paysOptions = computed(() =>
  pays.value.map((p) => ({
    label: `${p.nom} (${p.code})`,
    value: p.id,
    code: p.code,
    nom: p.nom,
  })),
)

const deviseOptions = computed(() =>
  devises.value.map((d) => ({
    label: `${d.symbole || d.code} — ${d.nom}`,
    value: d.id,
    code: d.code,
    symbole: d.symbole,
  })),
)

const tableRows = computed(() => [...draftRows.value, ...rows.value])

function normalizeList(data) {
  return Array.isArray(data) ? data : (data.items ?? [])
}

function mapLiaisonRow(l) {
  return {
    ...l,
    _key: l.id,
    _isNew: false,
    _isEditing: false,
    pays_id: l.pays_id,
    devise_id: l.devise_id,
    taux_defaut: Number(l.taux_defaut),
    is_defaut: Boolean(l.is_defaut),
  }
}

async function refreshSilently() {
  try {
    const { data } = await api.get('/pays-devise-liaisons')
    rows.value = normalizeList(data).map(mapLiaisonRow)
  } catch {
    // ignore — l'état local reste affiché
  }
}

async function load() {
  loading.value = true
  error.value = null
  rowErrors.value = {}
  try {
    const [p, d, l, s] = await Promise.all([
      api.get('/pays'),
      api.get('/devises'),
      api.get('/pays-devise-liaisons'),
      api.get('/settings'),
    ])
    pays.value = normalizeList(p.data)
    devises.value = normalizeList(d.data)
    rows.value = normalizeList(l.data).map(mapLiaisonRow)
    const settings = normalizeList(s.data)
    const locale = settings.find((x) => x.cle === 'DEVISE_LOCALE')
    if (locale) deviseLocale.value = locale.valeur
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le référentiel.'
  } finally {
    loading.value = false
  }
}

const { pending: savingLocale, run: saveDeviseLocale } = useAsyncAction(async () => {
  await api.put('/settings/DEVISE_LOCALE', { valeur: deviseLocale.value })
})

function createDraftRow() {
  draftRows.value.unshift({
    _key: `draft-${Date.now()}`,
    _isNew: true,
    _isEditing: true,
    id: null,
    pays_id: null,
    devise_id: null,
    taux_defaut: null,
    is_defaut: false,
    pays_code: null,
    pays_nom: null,
    devise_code: null,
    devise_symbole: null,
  })
}

function validateRow(row) {
  const errors = []
  if (!row.pays_id) errors.push('Pays requis')
  if (!row.devise_id) errors.push('Devise requise')
  if (row.taux_defaut == null || row.taux_defaut <= 0) errors.push('Taux invalide')
  return errors
}

function resolvePaysMeta(paysId) {
  const found = pays.value.find((p) => p.id === paysId)
  return found ? { code: found.code, nom: found.nom } : { code: null, nom: null }
}

function resolveDeviseMeta(deviseId) {
  const found = devises.value.find((d) => d.id === deviseId)
  return found ? { code: found.code, symbole: found.symbole } : { code: null, symbole: null }
}

async function saveRow(row) {
  const rowKey = row._key || row.id
  const errors = validateRow(row)
  if (errors.length) {
    rowErrors.value[rowKey] = errors.join(', ')
    return
  }
  rowErrors.value[rowKey] = null

  const payload = {
    pays_id: row.pays_id,
    devise_id: row.devise_id,
    taux_defaut: String(row.taux_defaut),
    is_defaut: Boolean(row.is_defaut),
  }

  savingRowKey.value = rowKey
  try {
    if (row._isNew) {
      const { data } = await api.post('/pays-devise-liaisons', payload)
      draftRows.value = draftRows.value.filter((r) => r._key !== row._key)
      rows.value.unshift(mapLiaisonRow(data))
    } else {
      const { data } = await api.put(`/pays-devise-liaisons/${row.id}`, payload)
      Object.assign(row, mapLiaisonRow(data))
    }
    if (payload.is_defaut) {
      await refreshSilently()
    }
  } catch (e) {
    rowErrors.value[rowKey] = e.response?.data?.error || 'Enregistrement impossible.'
  } finally {
    savingRowKey.value = null
  }
}

async function toggleDefault(row, value) {
  if (!props.canEdit || row._isNew) return
  const rowKey = row._key || row.id
  rowErrors.value[rowKey] = null
  togglingRowKey.value = rowKey
  try {
    await api.put(`/pays-devise-liaisons/${row.id}`, {
      pays_id: row.pays_id,
      devise_id: row.devise_id,
      taux_defaut: String(row.taux_defaut),
      is_defaut: value,
    })
    await refreshSilently()
  } catch (e) {
    rowErrors.value[rowKey] = e.response?.data?.error || 'Mise à jour impossible.'
  } finally {
    togglingRowKey.value = null
  }
}

function startEdit(row) {
  row._backup = {
    pays_id: row.pays_id,
    devise_id: row.devise_id,
    taux_defaut: row.taux_defaut,
    is_defaut: row.is_defaut,
  }
  row._isEditing = true
}

function cancelEdit(row) {
  if (row._isNew) {
    draftRows.value = draftRows.value.filter((r) => r._key !== row._key)
  } else if (row._backup) {
    Object.assign(row, row._backup)
    row._isEditing = false
  }
  rowErrors.value[row._key || row.id] = null
}

function confirmDelete(event, row) {
  confirm.require({
    target: event.currentTarget,
    message: `Supprimer la liaison ${row.pays_nom} → ${row.devise_code} ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: async () => {
      const rowKey = row._key || row.id
      deletingRowKey.value = rowKey
      try {
        await api.delete(`/pays-devise-liaisons/${row.id}`)
        rows.value = rows.value.filter((r) => r.id !== row.id)
      } finally {
        deletingRowKey.value = null
      }
    },
  })
}

function paysLabel(row) {
  if (row._isEditing) {
    const meta = resolvePaysMeta(row.pays_id)
    return meta.nom ? `${meta.nom} (${meta.code})` : null
  }
  return row.pays_nom ? `${row.pays_nom} (${row.pays_code})` : '—'
}

function paysCode(row) {
  if (row._isEditing) return resolvePaysMeta(row.pays_id).code
  return row.pays_code
}

function deviseMeta(row) {
  if (row._isEditing) return resolveDeviseMeta(row.devise_id)
  return { code: row.devise_code, symbole: row.devise_symbole }
}

function rowKey(data) {
  return data._key || data.id
}

onMounted(load)
</script>

<template>
  <div class="pays-devises-panel">
    <ConfirmPopup />

    <div v-if="canEdit" class="pays-devises-panel__locale">
      <label>Devise locale (source par défaut)</label>
      <Select
        v-model="deviseLocale"
        :options="deviseLocaleOptions"
        option-label="label"
        option-value="value"
        class="locale-select"
      />
      <Button label="Appliquer" icon="pi pi-save" size="small" :loading="savingLocale" @click="saveDeviseLocale" />
    </div>

    <div v-if="canEdit" class="pays-devises-panel__toolbar">
      <Button label="Ajouter une liaison" icon="pi pi-plus" size="small" @click="createDraftRow" />
    </div>

    <AppTableState
      :loading="loading"
      :error="error"
      :is-empty="!loading && !error && tableRows.length === 0"
      empty-title="Aucune liaison"
      empty-text="Ajoutez une liaison pays → devise avec le bouton ci-dessus."
      @retry="load"
    >
      <DataTable :value="tableRows" striped-rows data-key="_key">
        <Column header="Pays" style="min-width: 14rem">
          <template #body="{ data }">
            <div v-if="data._isEditing" class="cell-stack">
              <Select
                v-model="data.pays_id"
                :options="paysOptions"
                option-label="label"
                option-value="value"
                placeholder="Choisir un pays"
                filter
                fluid
              >
                <template #option="{ option }">
                  <span class="option-row">
                    <CountryFlag :code="option.code" :size="20" />
                    {{ option.label }}
                  </span>
                </template>
              </Select>
            </div>
            <div v-else class="pays-cell">
              <CountryFlag :code="paysCode(data)" :size="22" />
              <span>{{ paysLabel(data) }}</span>
            </div>
          </template>
        </Column>

        <Column header="Devise" style="min-width: 12rem">
          <template #body="{ data }">
            <div v-if="data._isEditing" class="cell-stack">
              <Select
                v-model="data.devise_id"
                :options="deviseOptions"
                option-label="label"
                option-value="value"
                placeholder="Choisir une devise"
                filter
                fluid
              />
            </div>
            <DeviseBadge
              v-else
              :code="deviseMeta(data).code"
              :symbole="deviseMeta(data).symbole"
            />
          </template>
        </Column>

        <Column style="min-width: 16rem">
          <template #header>
            <span>Taux défaut</span>
            <small class="column-hint">1 devise cible = … {{ deviseLocale }}</small>
          </template>
          <template #body="{ data }">
            <InputGroup v-if="data._isEditing">
              <InputGroupAddon>1 {{ deviseMeta(data).code || '?' }} =</InputGroupAddon>
              <InputNumber
                v-model="data.taux_defaut"
                :min="0"
                :min-fraction-digits="3"
                :max-fraction-digits="6"
                fluid
              />
              <InputGroupAddon>{{ deviseLocale }}</InputGroupAddon>
            </InputGroup>
            <span v-else>1 {{ data.devise_code }} = {{ data.taux_defaut }} {{ deviseLocale }}</span>
          </template>
        </Column>

        <Column header="Défaut" style="width: 5rem">
          <template #body="{ data }">
            <Checkbox
              v-if="data._isEditing || canEdit"
              :model-value="data.is_defaut"
              binary
              :disabled="!canEdit || data._isNew || togglingRowKey === rowKey(data)"
              @update:model-value="(v) => (data._isEditing ? (data.is_defaut = v) : toggleDefault(data, v))"
            />
            <i v-else :class="data.is_defaut ? 'pi pi-check text-green-500' : 'pi pi-minus text-muted'" />
          </template>
        </Column>

        <Column v-if="canEdit" header="Actions" style="width: 9rem">
          <template #body="{ data }">
            <div class="actions-cell">
              <template v-if="data._isEditing">
                <Button
                  icon="pi pi-check"
                  text
                  rounded
                  severity="success"
                  v-tooltip.top="'Enregistrer'"
                  :loading="savingRowKey === rowKey(data)"
                  :disabled="savingRowKey === rowKey(data)"
                  @click="saveRow(data)"
                />
                <Button
                  icon="pi pi-times"
                  text
                  rounded
                  severity="secondary"
                  v-tooltip.top="'Annuler'"
                  :disabled="savingRowKey === rowKey(data)"
                  @click="cancelEdit(data)"
                />
              </template>
              <template v-else>
                <Button icon="pi pi-pencil" text rounded v-tooltip.top="'Modifier'" @click="startEdit(data)" />
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  severity="danger"
                  v-tooltip.top="'Supprimer'"
                  :loading="deletingRowKey === rowKey(data)"
                  :disabled="deletingRowKey === rowKey(data)"
                  @click="confirmDelete($event, data)"
                />
              </template>
            </div>
            <Message
              v-if="rowErrors[data._key || data.id]"
              severity="error"
              size="small"
              variant="simple"
              class="row-error"
            >
              {{ rowErrors[data._key || data.id] }}
            </Message>
          </template>
        </Column>
      </DataTable>
    </AppTableState>
  </div>
</template>

<style scoped>
.pays-devises-panel__locale,
.pays-devises-panel__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.locale-select {
  min-width: 14rem;
}

.pays-cell,
.option-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.actions-cell {
  display: flex;
  gap: 0.15rem;
}

.cell-stack {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.row-error {
  margin-top: 0.35rem;
}

.text-green-500 {
  color: var(--p-green-500, #22c55e);
}

.text-muted {
  color: var(--layout-text-muted);
}

.column-hint {
  display: block;
  color: var(--layout-text-muted);
  font-size: 0.72rem;
}
</style>
