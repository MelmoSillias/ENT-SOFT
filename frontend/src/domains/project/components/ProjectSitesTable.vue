<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import OverlayPanel from 'primevue/overlaypanel'
import Checkbox from 'primevue/checkbox'
import MultiSelect from 'primevue/multiselect'
import Skeleton from 'primevue/skeleton'
import Textarea from 'primevue/textarea'
import Menu from 'primevue/menu'
import SplitButton from 'primevue/splitbutton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import AppCrudDialog from '@/domains/shared/components/AppCrudDialog.vue'
import {
  formatDateFr,
  projectSiteStatusLabel,
  projectSiteStatusSeverity,
  PROJECT_SITE_STATUS_OPTIONS,
} from '@/domains/shared/utils/entLabels'
import { updateSite, createSite, deleteSite } from '@/domains/site/services/siteService'
import {
  exportExcel,
  exportWord,
  exportClipboardImage,
} from '@/domains/project/composables/useProjectSiteExport'

// ─── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  sites: { type: Array, default: () => [] },
  sitesInformations: { type: Array, default: () => [] },
  lots: { type: Array, default: () => [] },
  projectId: { type: [String, Number], default: null },
})

const emit = defineEmits(['refresh'])

// ─── PrimeVue composables ────────────────────────────────────────────────────
const confirm = useConfirm()
const toast = useToast()

// ─── Constants ───────────────────────────────────────────────────────────────
const EXCLUDED_KEYS = new Set(['comment', 'remarques', 'status_source', 'status-source'])

// ─── Info columns ─────────────────────────────────────────────────────────────
const infoColumns = computed(() =>
  (props.sitesInformations ?? []).filter((col) => col?.key && !EXCLUDED_KEYS.has(col.key)),
)

// ─── localStorage settings key ────────────────────────────────────────────────
const storageKey = computed(() =>
  props.projectId ? `pst_settings_${props.projectId}` : null,
)

function loadSettings() {
  if (!storageKey.value) return null
  try {
    return JSON.parse(localStorage.getItem(storageKey.value) ?? 'null')
  } catch {
    return null
  }
}

function saveSettings(val) {
  if (!storageKey.value) return
  localStorage.setItem(storageKey.value, JSON.stringify(val))
}

// ─── Column visibility ────────────────────────────────────────────────────────
const FIXED_COLS = ['__status', '__comment', '__technician']
const ALL_EXTRA_COLS = computed(() => [
  ...infoColumns.value.map((c) => ({ key: c.key, label: c.label })),
  { key: '__status', label: 'Statut' },
  { key: '__comment', label: 'Commentaires' },
  { key: '__technician', label: 'Technicien' },
])

const savedSettings = loadSettings()
const visibleColKeys = ref(
  savedSettings?.visibleColKeys ?? ALL_EXTRA_COLS.value.map((c) => c.key),
)
const frozenCount = ref(savedSettings?.frozenCount ?? 2) // # + Code are always frozen
const statusFilter = ref(savedSettings?.statusFilter ?? [])

watch([visibleColKeys, frozenCount, statusFilter], () => {
  saveSettings({
    visibleColKeys: visibleColKeys.value,
    frozenCount: frozenCount.value,
    statusFilter: statusFilter.value,
  })
})

function isColVisible(key) {
  return visibleColKeys.value.includes(key)
}

// ─── Search ───────────────────────────────────────────────────────────────────
const searchQuery = ref('')

// ─── Grouped & filtered sites ─────────────────────────────────────────────────
const groupedSites = computed(() => {
  let allSites = props.sites ?? []

  // status filter
  if (statusFilter.value.length) {
    allSites = allSites.filter((s) => statusFilter.value.includes(s.status))
  }

  // text search
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    allSites = allSites.filter((s) => {
      if ((s.siteCode ?? '').toLowerCase().includes(q)) return true
      if ((s.siteTitle ?? '').toLowerCase().includes(q)) return true
      for (const val of Object.values(s.informationsValues ?? {})) {
        if (String(val ?? '').toLowerCase().includes(q)) return true
      }
      return false
    })
  }

  if (!props.lots?.length) {
    return [{ lot: null, sites: allSites }]
  }

  const lotOrder = props.lots.map((lot) => lot.code)
  const byLot = new Map(lotOrder.map((code) => [code, []]))
  const unassigned = []

  for (const site of allSites) {
    const code = site.lotCode
    if (code && byLot.has(code)) {
      byLot.get(code).push(site)
    } else {
      unassigned.push(site)
    }
  }

  const groups = props.lots.map((lot) => ({
    lot,
    sites: byLot.get(lot.code) ?? [],
  }))

  if (unassigned.length) {
    groups.push({ lot: { code: '__none', title: 'Sans lot' }, sites: unassigned })
  }

  return groups
})

const allFilteredSites = computed(() => groupedSites.value.flatMap((g) => g.sites))

// ─── Cell helpers ─────────────────────────────────────────────────────────────
function cellValue(site, key) {
  const raw = site.informationsValues?.[key]
  if (raw === null || raw === undefined || raw === '') return '—'
  if (typeof raw === 'boolean') return raw ? 'Oui' : 'Non'
  if (key.endsWith('_date') || key === 'start_date' || key === 'end_date') {
    return formatDateFr(raw)
  }
  return String(raw)
}

function lotLabel(lot) {
  if (!lot) return ''
  if (lot.code === '__none') return lot.title
  return lot.title ? `${lot.code} — ${lot.title}` : lot.code
}

// ─── Inline loading rows ──────────────────────────────────────────────────────
const loadingRowIds = ref(new Set())

function setRowLoading(siteId, val) {
  const s = new Set(loadingRowIds.value)
  if (val) s.add(siteId)
  else s.delete(siteId)
  loadingRowIds.value = s
}

// ─── Settings overlay ────────────────────────────────────────────────────────
const settingsPanel = ref(null)

function toggleSettings(event) {
  settingsPanel.value.toggle(event)
}

// ─── Status overlay (per row) ────────────────────────────────────────────────
const statusMenuRef = ref(null)
const statusMenuTarget = ref(null)

function openStatusMenu(event, site) {
  statusMenuTarget.value = site
  statusMenuRef.value.show(event)
}

const statusMenuItems = computed(() =>
  PROJECT_SITE_STATUS_OPTIONS.map((opt) => ({
    label: opt.label,
    icon:
      statusMenuTarget.value?.status === opt.value ? 'pi pi-check' : 'pi pi-circle',
    command: () => applyStatus(statusMenuTarget.value, opt.value),
  })),
)

async function applyStatus(site, newStatus) {
  if (!site || site.status === newStatus) return
  setRowLoading(site.id, true)
  try {
    await updateSite(site.id, { status: newStatus })
    site.status = newStatus
    toast.add({ severity: 'success', summary: 'Statut', detail: 'Mis à jour.', life: 2500 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e?.response?.data?.error || 'Impossible de modifier le statut.', life: 4000 })
  } finally {
    setRowLoading(site.id, false)
  }
}

// ─── Comment overlay ─────────────────────────────────────────────────────────
const commentPanel = ref(null)
const commentTarget = ref(null)
const commentText = ref('')
const savingComment = ref(false)

function openCommentPanel(event, site) {
  commentTarget.value = site
  commentText.value = site.comment ?? ''
  commentPanel.value.show(event)
}

async function saveComment() {
  if (!commentTarget.value) return
  savingComment.value = true
  const site = commentTarget.value
  try {
    await updateSite(site.id, { comment: commentText.value })
    site.comment = commentText.value
    commentPanel.value.hide()
    toast.add({ severity: 'success', summary: 'Commentaire', detail: 'Enregistré.', life: 2500 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e?.response?.data?.error || 'Erreur lors de l\'enregistrement.', life: 4000 })
  } finally {
    savingComment.value = false
  }
}

// ─── Delete ───────────────────────────────────────────────────────────────────
function askDelete(event, site) {
  confirm.require({
    target: event.currentTarget,
    message: `Supprimer « ${site.siteTitle || site.siteCode} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true, size: 'small' },
    acceptProps: { label: 'Supprimer', severity: 'danger', size: 'small' },
    accept: () => doDelete(site),
  })
}

async function doDelete(site) {
  setRowLoading(site.id, true)
  try {
    await deleteSite(site.id)
    toast.add({ severity: 'success', summary: 'Site', detail: 'Supprimé.', life: 2500 })
    emit('refresh')
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e?.response?.data?.error || 'Suppression impossible.', life: 4000 })
    setRowLoading(site.id, false)
  }
}

// ─── Edit / Add dialog ────────────────────────────────────────────────────────
const crudDialog = ref(false)
const crudMode = ref('create') // 'create' | 'edit'
const crudTarget = ref(null)
const crudForm = ref({})
const crudErrors = ref({})
const crudLoading = ref(false)

const crudFields = [
  { name: 'title', label: 'Titre du site', type: 'text', required: true },
  { name: 'description', label: 'Description', type: 'textarea' },
  {
    name: 'status',
    label: 'Statut',
    type: 'select',
    options: PROJECT_SITE_STATUS_OPTIONS.map((o) => ({ label: o.label, value: o.value })),
  },
]

function openCreate() {
  crudMode.value = 'create'
  crudTarget.value = null
  crudForm.value = { title: '', description: '', status: 'pending' }
  crudErrors.value = {}
  crudDialog.value = true
}

function openEdit(site) {
  crudMode.value = 'edit'
  crudTarget.value = site
  crudForm.value = {
    title: site.siteTitle ?? '',
    description: site.description ?? '',
    status: site.status ?? 'pending',
  }
  crudErrors.value = {}
  crudDialog.value = true
}

async function submitCrud() {
  crudErrors.value = {}
  if (!crudForm.value.title?.trim()) {
    crudErrors.value = { title: 'Le titre est requis.' }
    return
  }
  crudLoading.value = true
  try {
    if (crudMode.value === 'create') {
      await createSite({ ...crudForm.value })
    } else {
      await updateSite(crudTarget.value.id, { ...crudForm.value })
    }
    crudDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Site',
      detail: crudMode.value === 'create' ? 'Site ajouté.' : 'Site modifié.',
      life: 2500,
    })
    emit('refresh')
  } catch (e) {
    const err = e?.response?.data
    if (err?.fields) {
      crudErrors.value = err.fields
    } else {
      toast.add({ severity: 'error', summary: 'Erreur', detail: err?.error || 'Impossible d\'enregistrer.', life: 4000 })
    }
  } finally {
    crudLoading.value = false
  }
}

// ─── Export ───────────────────────────────────────────────────────────────────
const tableWrapperRef = ref(null)
const exporting = ref(false)

function buildExportColumns() {
  const cols = [
    { field: 'siteCode', label: 'Code site' },
    { field: 'siteTitle', label: 'Nom du site' },
  ]
  for (const c of infoColumns.value) {
    if (isColVisible(c.key)) cols.push({ key: c.key, label: c.label })
  }
  if (isColVisible('__status')) cols.push({ key: '__status', label: 'Statut' })
  if (isColVisible('__comment')) cols.push({ key: '__comment', label: 'Commentaires' })
  if (
    isColVisible('__technician') &&
    allFilteredSites.value.some((s) => s.technicianName)
  ) {
    cols.push({ key: '__technician', label: 'Technicien' })
  }
  return cols
}

const exportMenuItems = [
  {
    label: 'Excel (.xlsx)',
    icon: 'pi pi-file-excel',
    command: () => doExport('excel'),
  },
  {
    label: 'Word (.docx)',
    icon: 'pi pi-file-word',
    command: () => doExport('word'),
  },
  {
    label: 'Image (presse-papier)',
    icon: 'pi pi-image',
    command: () => doExport('image'),
  },
]

const exportMenuRef = ref(null)

async function doExport(format) {
  if (exporting.value) return
  exporting.value = true
  const columns = buildExportColumns()
  const enrichedGroups = groupedSites.value.map((g) => ({
    ...g,
    sites: g.sites.map((s) => ({ ...s, statusLabel: projectSiteStatusLabel(s.status) })),
  }))
  try {
    if (format === 'excel') {
      await exportExcel({ groupedSites: enrichedGroups, columns, projectTitle: 'projet' })
      toast.add({ severity: 'success', summary: 'Export Excel', detail: 'Fichier téléchargé.', life: 2500 })
    } else if (format === 'word') {
      await exportWord({ groupedSites: enrichedGroups, columns, projectTitle: 'projet' })
      toast.add({ severity: 'success', summary: 'Export Word', detail: 'Fichier téléchargé.', life: 2500 })
    } else if (format === 'image') {
      await nextTick()
      const el = tableWrapperRef.value
      if (!el) return
      const ok = await exportClipboardImage(el)
      if (ok) {
        toast.add({ severity: 'success', summary: 'Image copiée', detail: 'Tableau copié dans le presse-papier.', life: 2500 })
      } else {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de copier l\'image.', life: 4000 })
      }
    }
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <!-- ── Empty state ──────────────────────────────────────────────────────── -->
  <div v-if="!sites?.length" class="pst-empty">Aucun site associé.</div>

  <div v-else class="pst-root">

    <!-- ── Toolbar ──────────────────────────────────────────────────────────── -->
    <div class="pst-toolbar">
      <!-- Search -->
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="searchQuery"
          placeholder="Rechercher un site…"
          class="pst-search"
          size="small"
        />
      </IconField>

      <div class="pst-toolbar__right">
        <!-- Add button -->
        <Button
          icon="pi pi-plus"
          label="Ajouter"
          size="small"
          @click="openCreate"
        />

        <!-- Export button -->
        <Button
          icon="pi pi-download"
          label="Exporter"
          size="small"
          severity="secondary"
          outlined
          :loading="exporting"
          @click="exportMenuRef.toggle($event)"
        />
        <Menu ref="exportMenuRef" :model="exportMenuItems" popup />

        <!-- Settings button -->
        <Button
          icon="pi pi-sliders-h"
          size="small"
          severity="secondary"
          outlined
          v-tooltip.top="'Paramètres de la table'"
          @click="toggleSettings($event)"
        />
      </div>
    </div>

    <!-- ── Settings overlay ─────────────────────────────────────────────────── -->
    <OverlayPanel ref="settingsPanel" class="pst-settings-panel">
      <div class="pst-settings">
        <p class="pst-settings__title">Colonnes visibles</p>
        <div class="pst-settings__cols">
          <label v-for="col in ALL_EXTRA_COLS" :key="col.key" class="pst-settings__col-item">
            <Checkbox
              :model-value="visibleColKeys.includes(col.key)"
              binary
              @update:model-value="(v) => {
                if (v) { if (!visibleColKeys.includes(col.key)) visibleColKeys = [...visibleColKeys, col.key] }
                else { visibleColKeys = visibleColKeys.filter(k => k !== col.key) }
              }"
            />
            <span>{{ col.label }}</span>
          </label>
        </div>

        <p class="pst-settings__title">Colonnes figées (depuis gauche, hors #)</p>
        <div class="pst-settings__frozen">
          <Button
            v-for="n in [0, 1, 2, 3]"
            :key="n"
            :label="String(n)"
            size="small"
            :severity="frozenCount === n ? 'primary' : 'secondary'"
            :outlined="frozenCount !== n"
            @click="frozenCount = n"
          />
        </div>

        <p class="pst-settings__title">Filtrer par statut</p>
        <MultiSelect
          v-model="statusFilter"
          :options="PROJECT_SITE_STATUS_OPTIONS"
          option-label="label"
          option-value="value"
          placeholder="Tous les statuts"
          display="chip"
          fluid
          size="small"
        />
      </div>
    </OverlayPanel>

    <!-- ── Status context menu ────────────────────────────────────────────── -->
    <Menu ref="statusMenuRef" :model="statusMenuItems" popup />

    <!-- ── Comment overlay ────────────────────────────────────────────────── -->
    <OverlayPanel ref="commentPanel" class="pst-comment-panel">
      <div class="pst-comment">
        <p class="pst-settings__title">Commentaire</p>
        <Textarea v-model="commentText" rows="4" auto-resize fluid style="min-width: 18rem" />
        <div class="pst-comment__actions">
          <Button label="Annuler" size="small" severity="secondary" text @click="commentPanel.hide()" />
          <Button label="Enregistrer" size="small" :loading="savingComment" @click="saveComment" />
        </div>
      </div>
    </OverlayPanel>

    <!-- ── Tables by lot ──────────────────────────────────────────────────── -->
    <div ref="tableWrapperRef" class="pst-groups">
      <section
        v-for="group in groupedSites"
        :key="group.lot?.code ?? 'all'"
        class="pst-group"
      >
        <h3 v-if="group.lot" class="pst-lot-title">{{ lotLabel(group.lot) }}</h3>

        <DataTable
          :value="group.sites"
          scrollable
          scroll-height="flex"
          show-gridlines
          class="pst-table p-datatable-sm"
          size="small"
        >
          <!-- # numbering (always frozen) -->
          <Column header="#" frozen style="min-width:2.8rem; width:2.8rem; text-align:center">
            <template #body="{ index }">
              <span class="pst-num">{{ index + 1 }}</span>
            </template>
          </Column>

          <!-- Code site -->
          <Column
            field="siteCode"
            header="Code site"
            :frozen="frozenCount >= 1"
            style="min-width: 7rem"
          >
            <template #body="{ data }">
              <template v-if="loadingRowIds.has(data.id)">
                <Skeleton width="5rem" height="1rem" />
              </template>
              <template v-else>{{ data.siteCode }}</template>
            </template>
          </Column>

          <!-- Nom du site -->
          <Column
            field="siteTitle"
            header="Nom du site"
            :frozen="frozenCount >= 2"
            style="min-width: 12rem"
          >
            <template #body="{ data }">
              <template v-if="loadingRowIds.has(data.id)">
                <Skeleton width="9rem" height="1rem" />
              </template>
              <template v-else>{{ data.siteTitle }}</template>
            </template>
          </Column>

          <!-- Dynamic info columns -->
          <template v-for="(col, colIdx) in infoColumns" :key="col.key">
            <Column
              v-if="isColVisible(col.key)"
              :header="col.label"
              :frozen="frozenCount >= colIdx + 3"
              style="min-width: 8rem"
            >
              <template #body="{ data }">
                <template v-if="loadingRowIds.has(data.id)">
                  <Skeleton width="6rem" height="1rem" />
                </template>
                <template v-else>{{ cellValue(data, col.key) }}</template>
              </template>
            </Column>
          </template>

          <!-- Statut -->
          <Column v-if="isColVisible('__status')" header="Statut" style="min-width: 7rem">
            <template #body="{ data }">
              <template v-if="loadingRowIds.has(data.id)">
                <Skeleton width="5rem" height="1.5rem" border-radius="1rem" />
              </template>
              <template v-else>
                <Tag
                  :value="projectSiteStatusLabel(data.status)"
                  :severity="projectSiteStatusSeverity(data.status)"
                />
              </template>
            </template>
          </Column>

          <!-- Commentaires -->
          <Column v-if="isColVisible('__comment')" header="Commentaires" style="min-width: 14rem">
            <template #body="{ data }">
              <template v-if="loadingRowIds.has(data.id)">
                <Skeleton width="10rem" height="1rem" />
              </template>
              <template v-else>
                <span class="pst-comment-cell">{{ data.comment || '—' }}</span>
              </template>
            </template>
          </Column>

          <!-- Technicien -->
          <Column
            v-if="isColVisible('__technician') && sites.some((s) => s.technicianName)"
            header="Technicien"
            style="min-width: 9rem"
          >
            <template #body="{ data }">
              <template v-if="loadingRowIds.has(data.id)">
                <Skeleton width="7rem" height="1rem" />
              </template>
              <template v-else>{{ data.technicianName || '—' }}</template>
            </template>
          </Column>

          <!-- Options column -->
          <Column header="Options" frozen-align-last style="min-width: 8rem; width: 8rem">
            <template #body="{ data }">
              <div class="pst-actions">
                <!-- Edit -->
                <Button
                  icon="pi pi-pencil"
                  size="small"
                  text
                  rounded
                  severity="info"
                  v-tooltip.top="'Modifier'"
                  :disabled="loadingRowIds.has(data.id)"
                  @click="openEdit(data)"
                />
                <!-- Status picker -->
                <Button
                  icon="pi pi-tag"
                  size="small"
                  text
                  rounded
                  severity="secondary"
                  v-tooltip.top="'Changer le statut'"
                  :disabled="loadingRowIds.has(data.id)"
                  @click="openStatusMenu($event, data)"
                />
                <!-- Comment -->
                <Button
                  icon="pi pi-comment"
                  size="small"
                  text
                  rounded
                  severity="secondary"
                  v-tooltip.top="'Commentaire'"
                  :disabled="loadingRowIds.has(data.id)"
                  @click="openCommentPanel($event, data)"
                />
                <!-- Delete -->
                <Button
                  icon="pi pi-trash"
                  size="small"
                  text
                  rounded
                  severity="danger"
                  v-tooltip.top="'Supprimer'"
                  :disabled="loadingRowIds.has(data.id)"
                  @click="askDelete($event, data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </section>
    </div>

    <!-- ── No results ────────────────────────────────────────────────────────── -->
    <div v-if="allFilteredSites.length === 0" class="pst-empty pst-empty--filtered">
      Aucun site ne correspond à votre recherche.
    </div>

    <!-- ── CRUD Dialog ────────────────────────────────────────────────────────── -->
    <AppCrudDialog
      v-model:visible="crudDialog"
      :title="crudMode === 'create' ? 'Ajouter un site' : 'Modifier le site'"
      :fields="crudFields"
      v-model="crudForm"
      :loading="crudLoading"
      :field-errors="crudErrors"
      :submit-label="crudMode === 'create' ? 'Ajouter' : 'Enregistrer'"
      @submit="submitCrud"
    />
  </div>
</template>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.pst-root {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pst-groups {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.pst-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.pst-lot-title {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--layout-text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 0.1rem 0;
}

/* ── Empty states ────────────────────────────────────────────────────────── */
.pst-empty {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted, #64748b);
}

.pst-empty--filtered {
  font-size: 0.85rem;
  padding: 1rem;
}

/* ── Toolbar ─────────────────────────────────────────────────────────────── */
.pst-toolbar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.pst-toolbar__right {
  margin-left: auto;
  display: flex;
  gap: 0.4rem;
  align-items: center;
}

.pst-search {
  width: 14rem;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.pst-table :deep(.p-datatable-thead > tr > th) {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: var(--surface-100, #f1f5f9);
  color: var(--text-color, #1e293b);
  padding: 0.45rem 0.6rem;
  white-space: nowrap;
  border-color: var(--surface-300, #cbd5e1);
}

.pst-table :deep(.p-datatable-tbody > tr > td) {
  font-size: 0.79rem;
  padding: 0.35rem 0.6rem;
  border-color: var(--surface-200, #e2e8f0);
}

.pst-table :deep(.p-datatable-tbody > tr:hover > td) {
  background: var(--surface-50, #f8fafc);
}

/* ── Row number ──────────────────────────────────────────────────────────── */
.pst-num {
  font-size: 0.72rem;
  color: var(--layout-text-muted, #94a3b8);
  font-weight: 600;
  display: block;
  text-align: center;
}

/* ── Comment cell ────────────────────────────────────────────────────────── */
.pst-comment-cell {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.78rem;
}

/* ── Actions ─────────────────────────────────────────────────────────────── */
.pst-actions {
  display: flex;
  gap: 0.1rem;
  align-items: center;
}

/* ── Settings panel ──────────────────────────────────────────────────────── */
.pst-settings {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 18rem;
  max-width: 22rem;
}

.pst-settings__title {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--layout-text-muted, #64748b);
}

.pst-settings__cols {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.pst-settings__col-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  cursor: pointer;
}

.pst-settings__frozen {
  display: flex;
  gap: 0.4rem;
}

/* ── Comment panel ───────────────────────────────────────────────────────── */
.pst-comment {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.pst-comment__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.4rem;
}
</style>
