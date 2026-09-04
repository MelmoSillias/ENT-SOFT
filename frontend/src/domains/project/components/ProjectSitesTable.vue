<script setup>
import { computed, ref, watch, nextTick, onMounted } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Popover from 'primevue/popover'
import Checkbox from 'primevue/checkbox'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import Textarea from 'primevue/textarea'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import Divider from 'primevue/divider'
import AppTableActionsMenu from '@/domains/shared/components/AppTableActionsMenu.vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import {
  formatDateFr,
  projectSiteStatusLabel,
  projectSiteStatusSeverity,
  PROJECT_SITE_STATUS_OPTIONS,
} from '@/domains/shared/utils/entLabels'
import { listSites, createSite } from '@/domains/site/services/siteService'
import { listEmployees } from '@/domains/employee/services/employeeService'
import {
  addSiteToProject,
  updateProjectSite,
  removeSiteFromProject,
} from '@/domains/project/services/projectService'
import {
  exportExcel,
  exportWord,
  exportClipboardImage,
} from '@/domains/project/composables/useProjectSiteExport'
import { useAppBusyStore } from '@/domains/layout/stores/appBusy'

const props = defineProps({
  sites: { type: Array, default: () => [] },
  sitesInformations: { type: Array, default: () => [] },
  lots: { type: Array, default: () => [] },
  projectId: { type: [String, Number], default: null },
  clientId: { type: [String, Number], default: null },
})

const emit = defineEmits(['refresh'])

const confirm = useConfirm()
const toast = useToast()
const busyStore = useAppBusyStore()

const EXCLUDED_KEYS = new Set([
  'comment',
  'remarques',
  'status_source',
  'status-source',
  'status_raw',
])

const infoColumns = computed(() =>
  (props.sitesInformations ?? []).filter((col) => col?.key && !EXCLUDED_KEYS.has(col.key)),
)

const formInfoColumns = computed(() =>
  (props.sitesInformations ?? []).filter(
    (col) => col?.key && !EXCLUDED_KEYS.has(col.key) && col.key !== 'technicien' && col.key !== 'sources',
  ),
)

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

const ALL_EXTRA_COLS = computed(() => [
  ...infoColumns.value.map((c) => ({ key: c.key, label: c.label })),
  { key: '__technician', label: 'Techniciens' },
  { key: '__status', label: 'Statut' },
  { key: '__comment', label: 'Commentaires' },
])

function defaultVisibleColKeys() {
  return ALL_EXTRA_COLS.value.map((c) => c.key)
}

function applyLoadedSettings(saved) {
  visibleColKeys.value = saved?.visibleColKeys ?? defaultVisibleColKeys()
  statusFilter.value = saved?.statusFilter ?? []
  frozenUntilKey.value = saved?.frozenUntilKey ?? 'siteCode'
  searchQuery.value = saved?.searchQuery ?? ''
}

const initialSettings = loadSettings()
const visibleColKeys = ref(initialSettings?.visibleColKeys ?? defaultVisibleColKeys())
const statusFilter = ref(initialSettings?.statusFilter ?? [])

/** Key of the last frozen column (all previous are frozen too). */
const frozenUntilKey = ref(initialSettings?.frozenUntilKey ?? 'siteCode')
const searchQuery = ref(initialSettings?.searchQuery ?? '')
const fluidExpanded = ref(false)

/** Remount DataTable when freeze/visibility changes so PrimeVue recalculates sticky offsets. */
const tableLayoutKey = computed(
  () => `${frozenUntilKey.value}|${visibleColKeys.value.join(',')}`,
)

watch([visibleColKeys, frozenUntilKey, statusFilter, searchQuery], () => {
  saveSettings({
    visibleColKeys: visibleColKeys.value,
    frozenUntilKey: frozenUntilKey.value,
    statusFilter: statusFilter.value,
    searchQuery: searchQuery.value,
  })
})

watch(
  () => props.projectId,
  () => {
    applyLoadedSettings(loadSettings())
  },
)

function isColVisible(key) {
  return visibleColKeys.value.includes(key)
}

/** Ordered freezeable columns (left → right, excluding Options). */
const freezeableColumns = computed(() => {
  const cols = [
    { key: '__num', label: '#' },
    { key: 'siteCode', label: 'Code site' },
    { key: 'siteTitle', label: 'Nom du site' },
  ]
  for (const c of infoColumns.value) {
    if (isColVisible(c.key)) cols.push({ key: c.key, label: c.label })
  }
  if (isColVisible('__technician')) cols.push({ key: '__technician', label: 'Techniciens' })
  if (isColVisible('__status')) cols.push({ key: '__status', label: 'Statut' })
  if (isColVisible('__comment')) cols.push({ key: '__comment', label: 'Commentaires' })
  // Keep persisted freeze target selectable even if that column is currently hidden
  const until = frozenUntilKey.value
  if (until && !cols.some((c) => c.key === until)) {
    const fromExtra = ALL_EXTRA_COLS.value.find((c) => c.key === until)
    const fallbackLabel =
      until === '__num'
        ? '#'
        : until === 'siteCode'
          ? 'Code site'
          : until === 'siteTitle'
            ? 'Nom du site'
            : until
    cols.push(fromExtra ?? { key: until, label: fallbackLabel })
  }
  return cols
})

/** Full left→right order used to resolve freeze, including currently hidden columns. */
const fullFreezeOrder = computed(() => {
  const keys = ['__num', 'siteCode', 'siteTitle']
  for (const c of infoColumns.value) keys.push(c.key)
  keys.push('__technician', '__status', '__comment')
  return keys
})

function techniciansLabel(site) {
  const list = site?.technicians
  if (Array.isArray(list) && list.length) {
    return list.map((t) => t.name || t.id).filter(Boolean).join(', ')
  }
  return site?.technicianName || '—'
}

function isColumnFrozen(colKey) {
  const order = fullFreezeOrder.value
  let untilIdx = order.indexOf(frozenUntilKey.value)
  if (untilIdx < 0) untilIdx = order.indexOf('siteCode')
  const colIdx = order.indexOf(colKey)
  if (colIdx < 0) return false
  return colIdx <= Math.max(untilIdx, 0)
}

const groupedSites = computed(() => {
  let allSites = props.sites ?? []

  if (statusFilter.value.length) {
    allSites = allSites.filter((s) => statusFilter.value.includes(s.status))
  }

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
const hasSites = computed(() => (props.sites?.length ?? 0) > 0)

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

const loadingRowIds = ref(new Set())

function setRowLoading(siteId, val) {
  const s = new Set(loadingRowIds.value)
  if (val) s.add(siteId)
  else s.delete(siteId)
  loadingRowIds.value = s
}

function menuEvent(e) {
  return e?.originalEvent || e
}

function siteRowActions(data) {
  const busy = loadingRowIds.value.has(data.id)
  return [
    { label: 'Modifier', icon: 'pi pi-pencil', disabled: busy, command: () => openEdit(data) },
    { label: 'Dupliquer', icon: 'pi pi-copy', disabled: busy, command: () => openDuplicate(data) },
    { label: 'Techniciens', icon: 'pi pi-users', disabled: busy, command: () => openTechnicians(data) },
    { label: 'Changer le statut', icon: 'pi pi-tag', disabled: busy, command: (e) => openStatusMenu(menuEvent(e), data) },
    { label: 'Commentaire', icon: 'pi pi-comment', disabled: busy, command: (e) => openCommentPanel(menuEvent(e), data) },
    { label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', disabled: busy, command: (e) => askDelete(menuEvent(e), data) },
  ]
}

const settingsPanel = ref(null)
function toggleSettings(event) {
  settingsPanel.value.toggle(event)
}

const statusMenuRef = ref(null)
const statusMenuTarget = ref(null)

function openStatusMenu(event, site) {
  statusMenuTarget.value = site
  statusMenuRef.value.show(event?.originalEvent || event)
}

const statusMenuItems = computed(() =>
  PROJECT_SITE_STATUS_OPTIONS.map((opt) => ({
    label: opt.label,
    icon: statusMenuTarget.value?.status === opt.value ? 'pi pi-check' : 'pi pi-circle',
    command: () => applyStatus(statusMenuTarget.value, opt.value),
  })),
)

async function applyStatus(site, newStatus) {
  if (!site || site.status === newStatus) return
  setRowLoading(site.id, true)
  try {
    await updateProjectSite(site.id, { status: newStatus })
    site.status = newStatus
    toast.add({ severity: 'success', summary: 'Statut', detail: 'Mis à jour.', life: 2500 })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.error || 'Impossible de modifier le statut.',
      life: 4000,
    })
  } finally {
    setRowLoading(site.id, false)
  }
}

const commentPanel = ref(null)
const commentTarget = ref(null)
const commentText = ref('')
const savingComment = ref(false)

function openCommentPanel(event, site) {
  commentTarget.value = site
  commentText.value = site.comment ?? site.informationsValues?.comment ?? ''
  commentPanel.value.show(event?.originalEvent || event)
}

async function saveComment() {
  if (!commentTarget.value) return
  savingComment.value = true
  const site = commentTarget.value
  try {
    const nextValues = {
      ...(site.informationsValues ?? {}),
      comment: commentText.value,
    }
    await updateProjectSite(site.id, { informationsValues: nextValues })
    site.informationsValues = nextValues
    site.comment = commentText.value
    commentPanel.value.hide()
    toast.add({ severity: 'success', summary: 'Commentaire', detail: 'Enregistré.', life: 2500 })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.error || "Erreur lors de l'enregistrement.",
      life: 4000,
    })
  } finally {
    savingComment.value = false
  }
}

function askDelete(event, site) {
  confirm.require({
    target: event?.currentTarget,
    message: `Retirer « ${site.siteTitle || site.siteCode} » du projet ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true, size: 'small' },
    acceptProps: { label: 'Supprimer', severity: 'danger', size: 'small' },
    accept: () => doDelete(site),
  })
}

async function doDelete(site) {
  setRowLoading(site.id, true)
  try {
    await removeSiteFromProject(site.id)
    toast.add({ severity: 'success', summary: 'Site', detail: 'Retiré du projet.', life: 2500 })
    emit('refresh')
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.error || 'Suppression impossible.',
      life: 4000,
    })
    setRowLoading(site.id, false)
  }
}

// ─── Add / Edit dialog ────────────────────────────────────────────────────────
const crudDialog = ref(false)
const crudMode = ref('create')
const crudTarget = ref(null)
const crudLoading = ref(false)
const crudErrors = ref({})
const siteMode = ref('existing') // 'existing' | 'new'
const selectedSiteId = ref(null)
const newSiteTitle = ref('')
const newSiteCode = ref('')
const siteOptions = ref([])
const sitesLoading = ref(false)

const crudStatus = ref('pending')
const crudInfoValues = ref({})
const crudComment = ref('')
const crudEmployeeIds = ref([])

async function loadSiteOptions() {
  sitesLoading.value = true
  try {
    const items = await listSites()
    const linkedIds = new Set((props.sites ?? []).map((s) => s.siteId).filter(Boolean))
    const allowLinked = crudMode.value === 'edit'
    siteOptions.value = items
      .filter((s) => allowLinked || !linkedIds.has(s.id))
      .map((s) => ({
        label: `${s.code} — ${s.title}`,
        value: s.id,
        code: s.code,
        title: s.title,
      }))
  } catch {
    siteOptions.value = []
  } finally {
    sitesLoading.value = false
  }
}

function emptyInfoValues() {
  const vals = {}
  for (const col of formInfoColumns.value) {
    vals[col.key] = ''
  }
  return vals
}

function openCreate() {
  crudMode.value = 'create'
  crudTarget.value = null
  siteMode.value = 'existing'
  selectedSiteId.value = null
  newSiteTitle.value = ''
  newSiteCode.value = ''
  crudStatus.value = 'pending'
  crudInfoValues.value = emptyInfoValues()
  crudComment.value = ''
  crudEmployeeIds.value = []
  crudErrors.value = {}
  crudDialog.value = true
  loadSiteOptions()
}

function openEdit(site) {
  crudMode.value = 'edit'
  crudTarget.value = site
  siteMode.value = 'existing'
  selectedSiteId.value = site.siteId ?? null
  newSiteTitle.value = ''
  newSiteCode.value = ''
  crudStatus.value = site.status ?? 'pending'
  const vals = emptyInfoValues()
  for (const col of formInfoColumns.value) {
    const raw = site.informationsValues?.[col.key]
    vals[col.key] = raw === null || raw === undefined ? '' : String(raw)
  }
  crudInfoValues.value = vals
  crudComment.value = site.comment ?? site.informationsValues?.comment ?? ''
  crudEmployeeIds.value = [...(site.employeeIds ?? [])]
  crudErrors.value = {}
  crudDialog.value = true
}

function openDuplicate(site) {
  crudMode.value = 'duplicate'
  crudTarget.value = site
  siteMode.value = 'existing'
  selectedSiteId.value = null
  newSiteTitle.value = ''
  newSiteCode.value = ''
  crudStatus.value = site.status ?? 'pending'
  const vals = emptyInfoValues()
  for (const col of formInfoColumns.value) {
    const raw = site.informationsValues?.[col.key]
    vals[col.key] = raw === null || raw === undefined ? '' : String(raw)
  }
  crudInfoValues.value = vals
  crudComment.value = site.comment ?? site.informationsValues?.comment ?? ''
  crudEmployeeIds.value = [...(site.employeeIds ?? [])]
  crudErrors.value = {}
  crudDialog.value = true
  loadSiteOptions()
}

function buildInformationsValues(preserveExisting = null) {
  const values = {
    ...(preserveExisting ?? {}),
    ...crudInfoValues.value,
  }
  for (const key of Object.keys(crudInfoValues.value)) {
    if (values[key] === '') values[key] = null
  }
  if (crudComment.value?.trim()) {
    values.comment = crudComment.value.trim()
  } else {
    values.comment = null
  }
  return values
}

async function submitCrud() {
  crudErrors.value = {}

  const isCreateLike = crudMode.value === 'create' || crudMode.value === 'duplicate'
  if (isCreateLike) {
    if ((crudMode.value === 'duplicate' || siteMode.value === 'existing') && !selectedSiteId.value) {
      crudErrors.value = { site: 'Sélectionnez un site.' }
      return
    }
    if (crudMode.value === 'create' && siteMode.value === 'new') {
      const errs = {}
      if (!newSiteCode.value.trim()) errs.code = 'Le code du nouveau site est requis.'
      if (!newSiteTitle.value.trim()) errs.title = 'Le titre du nouveau site est requis.'
      if (Object.keys(errs).length) {
        crudErrors.value = errs
        return
      }
    }
    if (!props.projectId) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Projet introuvable.', life: 4000 })
      return
    }
  }

  crudLoading.value = true
  try {
    const informationsValues =
      crudMode.value === 'edit'
        ? buildInformationsValues(crudTarget.value?.informationsValues ?? {})
        : buildInformationsValues(
            crudMode.value === 'duplicate' ? (crudTarget.value?.informationsValues ?? {}) : null,
          )

    if (isCreateLike) {
      let siteId = selectedSiteId.value
      if (crudMode.value === 'create' && siteMode.value === 'new') {
        const created = await createSite({
          code: newSiteCode.value.trim(),
          title: newSiteTitle.value.trim(),
          clientId: props.clientId || null,
        })
        siteId = created.id
      } else if (!siteId) {
        crudErrors.value = { site: 'Sélectionnez un site.' }
        crudLoading.value = false
        return
      }
      const payload = {
        siteId,
        status: crudStatus.value,
        informationsValues,
        employeeIds: crudEmployeeIds.value,
      }
      if (crudEmployeeIds.value.length) {
        payload.technicianId = crudEmployeeIds.value[0]
      }
      await addSiteToProject(props.projectId, payload)
    } else {
      await updateProjectSite(crudTarget.value.id, {
        status: crudStatus.value,
        informationsValues,
      })
    }

    crudDialog.value = false
    const detail =
      crudMode.value === 'create'
        ? 'Site ajouté au projet.'
        : crudMode.value === 'duplicate'
          ? 'Site dupliqué.'
          : 'Site modifié.'
    toast.add({
      severity: 'success',
      summary: 'Site',
      detail,
      life: 2500,
    })
    emit('refresh')
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.error || "Impossible d'enregistrer.",
      life: 4000,
    })
  } finally {
    crudLoading.value = false
  }
}

// ─── Technicians dialog ───────────────────────────────────────────────────────
const techDialog = ref(false)
const techTarget = ref(null)
const techLoading = ref(false)
const techSaving = ref(false)
const techEmployeeIds = ref([])
const employeeOptions = ref([])

async function loadEmployeeOptions() {
  techLoading.value = true
  try {
    const items = await listEmployees()
    employeeOptions.value = (items ?? []).map((e) => ({
      label: e.name,
      value: e.id,
    }))
  } catch {
    employeeOptions.value = []
  } finally {
    techLoading.value = false
  }
}

function openTechnicians(site) {
  techTarget.value = site
  techEmployeeIds.value = [...(site.employeeIds ?? [])]
  techDialog.value = true
  loadEmployeeOptions()
}

async function saveTechnicians() {
  if (!techTarget.value) return
  techSaving.value = true
  try {
    const employeeIds = [...techEmployeeIds.value]
    const payload = {
      employeeIds,
      technicianId: employeeIds.length ? employeeIds[0] : null,
    }
    await updateProjectSite(techTarget.value.id, payload)
    techDialog.value = false
    toast.add({ severity: 'success', summary: 'Techniciens', detail: 'Mis à jour.', life: 2500 })
    emit('refresh')
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: e?.response?.data?.error || 'Impossible de mettre à jour les techniciens.',
      life: 4000,
    })
  } finally {
    techSaving.value = false
  }
}

const crudDialogHeader = computed(() => {
  if (crudMode.value === 'edit') return 'Modifier le site du projet'
  if (crudMode.value === 'duplicate') return 'Dupliquer le site du projet'
  return 'Ajouter un site au projet'
})

const crudSubmitLabel = computed(() => {
  if (crudMode.value === 'edit') return 'Enregistrer'
  if (crudMode.value === 'duplicate') return 'Dupliquer'
  return 'Ajouter'
})

// ─── Export ───────────────────────────────────────────────────────────────────
const tableWrapperRef = ref(null)
const exporting = ref(false)
const exportMenuRef = ref(null)

function buildExportColumns() {
  const cols = [
    { field: 'siteCode', label: 'Code site' },
    { field: 'siteTitle', label: 'Nom du site' },
  ]
  for (const c of infoColumns.value) {
    if (isColVisible(c.key)) cols.push({ key: c.key, label: c.label })
  }
  if (isColVisible('__technician')) cols.push({ key: '__technician', label: 'Techniciens' })
  if (isColVisible('__status')) cols.push({ key: '__status', label: 'Statut' })
  if (isColVisible('__comment')) cols.push({ key: '__comment', label: 'Commentaires' })
  return cols
}

const exportMenuItems = [
  { label: 'Excel (.xlsx)', icon: 'pi pi-file-excel', command: () => doExport('excel') },
  { label: 'Word (.docx)', icon: 'pi pi-file-word', command: () => doExport('word') },
  { label: 'Image (presse-papier)', icon: 'pi pi-image', command: () => doExport('image') },
]

async function doExport(format) {
  if (exporting.value) return
  exporting.value = true
  busyStore.startExport(format === 'excel' ? 'Export Excel…' : format === 'word' ? 'Export Word…' : 'Export image…')
  const columns = buildExportColumns()
  const enrichedGroups = groupedSites.value.map((g) => ({
    ...g,
    sites: g.sites.map((s) => ({
      ...s,
      statusLabel: projectSiteStatusLabel(s.status),
      status: s.status,
      technicianName: techniciansLabel(s) === '—' ? '' : techniciansLabel(s),
    })),
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
      toast.add({
        severity: ok ? 'success' : 'error',
        summary: ok ? 'Image copiée' : 'Erreur',
        detail: ok ? 'Tableau copié dans le presse-papier.' : "Impossible de copier l'image.",
        life: 2500,
      })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Export',
      detail: e?.message || "Échec de l'export.",
      life: 4000,
    })
  } finally {
    exporting.value = false
    busyStore.endExport()
  }
}

onMounted(() => {
  // Re-apply persisted settings once mounted (visible tab) so freeze is effective immediately
  applyLoadedSettings(loadSettings())

  // Ensure newly added info columns appear in visibility list
  const saved = loadSettings()
  const known = new Set(visibleColKeys.value)
  for (const col of ALL_EXTRA_COLS.value) {
    if (!known.has(col.key) && !saved?.visibleColKeys) {
      visibleColKeys.value = [...visibleColKeys.value, col.key]
    }
  }
})
</script>

<template>
  <div class="pst-root">
    <!-- Toolbar (always visible) -->
    <div class="pst-toolbar">
      <IconField v-if="hasSites">
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="searchQuery"
          placeholder="Rechercher un site…"
          class="pst-search"
          size="small"
        />
      </IconField>

      <div class="pst-toolbar__right">
        <Button icon="pi pi-plus" label="Ajouter" size="small" @click="openCreate" />

        <Button
          v-if="hasSites"
          icon="pi pi-download"
          label="Exporter"
          size="small"
          severity="secondary"
          outlined
          :loading="exporting"
          @click="exportMenuRef.toggle($event)"
        />
        <Menu ref="exportMenuRef" :model="exportMenuItems" popup />

        <Button
          v-if="hasSites"
          :icon="fluidExpanded ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
          size="small"
          severity="secondary"
          outlined
          v-tooltip.top="fluidExpanded ? 'Réduire' : 'Agrandir (plein écran)'"
          @click="fluidExpanded = !fluidExpanded"
        />

        <Button
          v-if="hasSites"
          icon="pi pi-sliders-h"
          size="small"
          severity="secondary"
          outlined
          v-tooltip.top="'Paramètres de la table'"
          @click="toggleSettings($event)"
        />
      </div>
    </div>

    <div v-if="!hasSites" class="pst-empty">Aucun site associé.</div>

    <template v-else>
      <!-- Settings popover -->
      <Popover ref="settingsPanel" class="pst-settings-panel">
        <div class="pst-settings">
          <p class="pst-settings__title">Colonnes visibles</p>
          <div class="pst-settings__cols">
            <label v-for="col in ALL_EXTRA_COLS" :key="col.key" class="pst-settings__col-item">
              <Checkbox
                :model-value="visibleColKeys.includes(col.key)"
                binary
                @update:model-value="(v) => {
                  if (v) {
                    if (!visibleColKeys.includes(col.key)) visibleColKeys = [...visibleColKeys, col.key]
                  } else {
                    visibleColKeys = visibleColKeys.filter((k) => k !== col.key)
                  }
                }"
              />
              <span>{{ col.label }}</span>
            </label>
          </div>

          <p class="pst-settings__title">Figer jusqu’à la colonne</p>
          <Select
            v-model="frozenUntilKey"
            :options="freezeableColumns"
            option-label="label"
            option-value="key"
            placeholder="Choisir une colonne"
            fluid
            size="small"
          />
          <small class="pst-settings__hint">
            Toutes les colonnes à gauche (incluses) restent figées au défilement.
          </small>

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
      </Popover>

      <Menu ref="statusMenuRef" :model="statusMenuItems" popup />

      <Popover ref="commentPanel" class="pst-comment-panel">
        <div class="pst-comment">
          <p class="pst-settings__title">Commentaire</p>
          <Textarea v-model="commentText" rows="4" auto-resize fluid style="min-width: 18rem" />
          <div class="pst-comment__actions">
            <Button label="Annuler" size="small" severity="secondary" text @click="commentPanel.hide()" />
            <Button label="Enregistrer" size="small" :loading="savingComment" @click="saveComment" />
          </div>
        </div>
      </Popover>

      <div v-show="!fluidExpanded" ref="tableWrapperRef" class="pst-groups">
        <section
          v-for="group in groupedSites"
          :key="group.lot?.code ?? 'all'"
          class="pst-group"
        >
          <h3 v-if="group.lot" class="pst-lot-title">{{ lotLabel(group.lot) }}</h3>

          <DataTable
            :key="'pst-' + (group.lot?.code ?? 'all') + '-' + tableLayoutKey"
            :value="group.sites"
            scrollable
            scroll-height="flex"
            show-gridlines
            class="pst-table p-datatable-sm"
            size="small"
          >
            <Column
              header="#"
              :frozen="isColumnFrozen('__num')"
              style="min-width:2.2rem; width:2.2rem; text-align:center"
            >
              <template #body="{ index }">
                <span class="pst-num">{{ index + 1 }}</span>
              </template>
            </Column>

            <Column
              field="siteCode"
              header="Code site"
              :frozen="isColumnFrozen('siteCode')"
              style="min-width: 5.5rem"
            >
              <template #body="{ data }">
                <Skeleton v-if="loadingRowIds.has(data.id)" width="5rem" height="1rem" />
                <template v-else>{{ data.siteCode }}</template>
              </template>
            </Column>

            <Column
              field="siteTitle"
              header="Nom du site"
              :frozen="isColumnFrozen('siteTitle')"
              style="min-width: 9rem"
            >
              <template #body="{ data }">
                <Skeleton v-if="loadingRowIds.has(data.id)" width="9rem" height="1rem" />
                <template v-else>{{ data.siteTitle }}</template>
              </template>
            </Column>

            <template v-for="col in infoColumns" :key="col.key">
              <Column
                v-if="isColVisible(col.key)"
                :header="col.label"
                :frozen="isColumnFrozen(col.key)"
                style="min-width: 6rem"
              >
                <template #body="{ data }">
                  <Skeleton v-if="loadingRowIds.has(data.id)" width="6rem" height="1rem" />
                  <template v-else>{{ cellValue(data, col.key) }}</template>
                </template>
              </Column>
            </template>

            <Column
              v-if="isColVisible('__technician')"
              header="Techniciens"
              :frozen="isColumnFrozen('__technician')"
              style="min-width: 7.5rem"
            >
              <template #body="{ data }">
                <Skeleton v-if="loadingRowIds.has(data.id)" width="7rem" height="1rem" />
                <template v-else>{{ techniciansLabel(data) }}</template>
              </template>
            </Column>

            <Column
              v-if="isColVisible('__status')"
              header="Statut"
              :frozen="isColumnFrozen('__status')"
              style="min-width: 5.5rem"
            >
              <template #body="{ data }">
                <Skeleton v-if="loadingRowIds.has(data.id)" width="5rem" height="1.5rem" border-radius="1rem" />
                <Tag
                  v-else
                  :value="projectSiteStatusLabel(data.status)"
                  :severity="projectSiteStatusSeverity(data.status)"
                />
              </template>
            </Column>

            <Column
              v-if="isColVisible('__comment')"
              header="Commentaires"
              :frozen="isColumnFrozen('__comment')"
              style="min-width: 10rem"
            >
              <template #body="{ data }">
                <Skeleton v-if="loadingRowIds.has(data.id)" width="10rem" height="1rem" />
                <span v-else class="pst-comment-cell">{{ data.comment || '—' }}</span>
              </template>
            </Column>

            <Column header="" style="min-width: 2.75rem; width: 2.75rem">
              <template #body="{ data }">
                <AppTableActionsMenu menu-only :actions="siteRowActions(data)" aria-label="Options du site" />
              </template>
            </Column>
          </DataTable>
        </section>
      </div>

      <Dialog
        v-model:visible="fluidExpanded"
        modal
        maximizable
        :maximized="true"
        header="Sites du projet"
        class="pst-fluid-dialog"
        :style="{ width: '98vw' }"
        :content-style="{ overflow: 'auto', maxHeight: 'calc(100vh - 7rem)' }"
      >
        <div class="pst-groups">
          <section
            v-for="group in groupedSites"
            :key="'fluid-' + (group.lot?.code ?? 'all')"
            class="pst-group"
          >
            <h3 v-if="group.lot" class="pst-lot-title">{{ lotLabel(group.lot) }}</h3>
            <DataTable
              :key="'pst-fluid-' + (group.lot?.code ?? 'all') + '-' + tableLayoutKey"
              :value="group.sites"
              scrollable
              scroll-height="flex"
              show-gridlines
              class="pst-table p-datatable-sm"
              size="small"
            >
              <Column header="#" :frozen="isColumnFrozen('__num')" style="min-width:2.2rem; width:2.2rem; text-align:center">
                <template #body="{ index }"><span class="pst-num">{{ index + 1 }}</span></template>
              </Column>
              <Column field="siteCode" header="Code site" :frozen="isColumnFrozen('siteCode')" style="min-width: 5.5rem">
                <template #body="{ data }">{{ data.siteCode }}</template>
              </Column>
              <Column field="siteTitle" header="Nom du site" :frozen="isColumnFrozen('siteTitle')" style="min-width: 9rem">
                <template #body="{ data }">{{ data.siteTitle }}</template>
              </Column>
              <template v-for="col in infoColumns" :key="'f-' + col.key">
                <Column v-if="isColVisible(col.key)" :header="col.label" :frozen="isColumnFrozen(col.key)" style="min-width: 6rem">
                  <template #body="{ data }">{{ cellValue(data, col.key) }}</template>
                </Column>
              </template>
              <Column
                v-if="isColVisible('__technician')"
                header="Techniciens"
                :frozen="isColumnFrozen('__technician')"
                style="min-width: 7.5rem"
              >
                <template #body="{ data }">{{ techniciansLabel(data) }}</template>
              </Column>
              <Column v-if="isColVisible('__status')" header="Statut" :frozen="isColumnFrozen('__status')" style="min-width: 5.5rem">
                <template #body="{ data }">
                  <Tag :value="projectSiteStatusLabel(data.status)" :severity="projectSiteStatusSeverity(data.status)" />
                </template>
              </Column>
              <Column v-if="isColVisible('__comment')" header="Commentaires" :frozen="isColumnFrozen('__comment')" style="min-width: 10rem">
                <template #body="{ data }"><span class="pst-comment-cell">{{ data.comment || '—' }}</span></template>
              </Column>
              <Column header="" style="min-width: 2.75rem; width: 2.75rem">
                <template #body="{ data }">
                  <AppTableActionsMenu menu-only :actions="siteRowActions(data)" aria-label="Options du site" />
                </template>
              </Column>
            </DataTable>
          </section>
        </div>
      </Dialog>

      <div v-if="allFilteredSites.length === 0" class="pst-empty pst-empty--filtered">
        Aucun site ne correspond à votre recherche.
      </div>
    </template>

    <!-- Add / Edit / Duplicate dialog -->
    <Dialog
      v-model:visible="crudDialog"
      :header="crudDialogHeader"
      modal
      style="width: min(560px, 95vw)"
    >
      <div class="pst-dialog">
        <template v-if="crudMode === 'create' || crudMode === 'duplicate'">
          <p v-if="crudMode === 'duplicate'" class="pst-dialog__hint">
            Les données sont reprises. Choisissez un autre site (déjà liés exclus).
          </p>
          <div v-if="crudMode === 'create'" class="pst-dialog__mode">
            <Button
              label="Site existant"
              size="small"
              :severity="siteMode === 'existing' ? 'primary' : 'secondary'"
              :outlined="siteMode !== 'existing'"
              @click="siteMode = 'existing'"
            />
            <Button
              label="Nouveau site"
              size="small"
              :severity="siteMode === 'new' ? 'primary' : 'secondary'"
              :outlined="siteMode !== 'new'"
              @click="siteMode = 'new'"
            />
          </div>

          <div v-if="crudMode === 'duplicate' || siteMode === 'existing'" class="field">
            <label>Site <span class="required">*</span></label>
            <Select
              v-model="selectedSiteId"
              :options="siteOptions"
              option-label="label"
              option-value="value"
              placeholder="Rechercher par code ou titre…"
              filter
              filter-placeholder="Code ou titre"
              :loading="sitesLoading"
              :invalid="Boolean(crudErrors.site)"
              fluid
            />
            <small v-if="crudErrors.site" class="pst-dialog__error">{{ crudErrors.site }}</small>
          </div>

          <div v-else class="pst-dialog__new-site">
            <div class="field">
              <label>Code du site <span class="required">*</span></label>
              <InputText
                v-model="newSiteCode"
                :invalid="Boolean(crudErrors.code)"
                placeholder="Ex. SIT-0042"
                fluid
              />
              <small v-if="crudErrors.code" class="pst-dialog__error">{{ crudErrors.code }}</small>
            </div>
            <div class="field">
              <label>Titre du nouveau site <span class="required">*</span></label>
              <InputText
                v-model="newSiteTitle"
                :invalid="Boolean(crudErrors.title)"
                placeholder="Ex. Site Nord"
                fluid
              />
              <small v-if="crudErrors.title" class="pst-dialog__error">{{ crudErrors.title }}</small>
            </div>
            <small class="pst-dialog__hint">Le site sera créé puis lié au projet. Le code doit être unique.</small>
          </div>
        </template>

        <template v-else>
          <div class="field">
            <label>Site</label>
            <InputText
              :model-value="`${crudTarget?.siteCode ?? ''} — ${crudTarget?.siteTitle ?? ''}`"
              disabled
              fluid
            />
          </div>
        </template>

        <div class="field">
          <label>Statut</label>
          <Select
            v-model="crudStatus"
            :options="PROJECT_SITE_STATUS_OPTIONS"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>

        <template v-if="formInfoColumns.length">
          <Divider />
          <p class="pst-dialog__section">Informations supplémentaires</p>
          <div
            v-for="col in formInfoColumns"
            :key="col.key"
            class="field"
          >
            <label>{{ col.label }}</label>
            <InputText v-model="crudInfoValues[col.key]" fluid />
          </div>
          <Divider />
        </template>

        <div class="field">
          <label>Commentaire</label>
          <Textarea v-model="crudComment" rows="3" auto-resize fluid />
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="crudLoading" @click="crudDialog = false" />
        <Button
          :label="crudSubmitLabel"
          icon="pi pi-check"
          :loading="crudLoading"
          @click="submitCrud"
        />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="techDialog"
      header="Techniciens du site"
      modal
      style="width: min(480px, 95vw)"
    >
      <div class="field">
        <label>Techniciens</label>
        <MultiSelect
          v-model="techEmployeeIds"
          :options="employeeOptions"
          option-label="label"
          option-value="value"
          placeholder="Sélectionner des techniciens"
          filter
          display="chip"
          :loading="techLoading"
          fluid
        />
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="techSaving" @click="techDialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="techSaving" @click="saveTechnicians" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.pst-root {
  --pst-header-bg: var(--p-datatable-header-cell-background, var(--p-datatable-header-background));
  --pst-row-bg: var(--p-datatable-body-cell-background);
  --pst-row-hover-bg: var(--p-datatable-row-hover-background);
  --pst-border: var(--pv-surface-border, var(--layout-panel-border));
  --pst-text: var(--pv-text, var(--layout-text-color));
  --pst-text-muted: var(--pv-text-muted, var(--layout-text-muted));

  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  color: var(--pst-text);
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
  color: var(--pst-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 0.1rem 0;
}

.pst-empty {
  padding: 2rem;
  text-align: center;
  color: var(--pst-text-muted);
  border: 1px dashed color-mix(in srgb, var(--pst-border) 78%, transparent);
  border-radius: var(--layout-radius-lg, 0.5rem);
  background: color-mix(in srgb, var(--layout-panel-bg) 88%, transparent);
}

.pst-empty--filtered {
  font-size: 0.85rem;
  padding: 1rem;
}

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

.pst-table {
  border-radius: var(--layout-radius-sm, 0.5rem);
  /* Do not set overflow:hidden here — it breaks position:sticky on frozen columns */
}

.pst-table :deep(.p-datatable-table-container),
.pst-table :deep(.p-datatable-wrapper) {
  border: 1px solid var(--pst-border);
  border-radius: var(--layout-radius-sm, 0.5rem);
  background: var(--pst-row-bg);
}

.pst-table :deep(.p-datatable-thead > tr > th) {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  background: var(--pst-header-bg);
  color: var(--pst-text);
  padding: 0.35rem 0.45rem !important;
  white-space: nowrap;
  border-color: var(--pst-border);
}

.pst-table :deep(.p-datatable-tbody > tr) {
  background: transparent;
  color: var(--pst-text);
}

.pst-table :deep(.p-datatable-tbody > tr > td) {
  font-size: 0.75rem;
  padding: 0.3rem 0.45rem !important;
  line-height: 1.25;
  border-color: var(--pst-border);
  color: var(--pst-text);
  background: transparent;
}

.pst-table :deep(.p-datatable-tbody > tr:hover) {
  background: var(--pst-row-hover-bg);
}

.pst-table :deep(.p-datatable-tbody > tr:hover > td) {
  background: transparent;
}

/* Opaque frozen columns so scrolled cells don’t show through */
.pst-table :deep(td.p-datatable-frozen-column) {
  background: var(--pst-row-bg) !important;
}

.pst-table :deep(th.p-datatable-frozen-column) {
  background: var(--pst-header-bg) !important;
}

.pst-table :deep(.p-datatable-tbody > tr:hover > td.p-datatable-frozen-column) {
  background: var(--pst-row-hover-bg) !important;
}

/* Right edge of the frozen block — stays visible while scrolling for contrast */
.pst-table :deep(th.p-datatable-frozen-column:has(+ :not(.p-datatable-frozen-column))),
.pst-table :deep(td.p-datatable-frozen-column:has(+ :not(.p-datatable-frozen-column))) {
  border-right: 1px solid var(--pst-border) !important;
  box-shadow:
    1px 0 0 0 var(--pst-border),
    3px 0 4px -2px color-mix(in srgb, var(--pst-text) 12%, transparent);
}

.pst-table :deep(.p-tag) {
  font-size: 0.65rem;
  padding: 0.1rem 0.4rem;
}

.pst-num {
  font-size: 0.65rem;
  color: var(--pst-text-muted);
  font-weight: 600;
  display: block;
  text-align: center;
}

.pst-comment-cell {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.7rem;
  color: var(--pst-text);
}

.pst-actions {
  display: flex;
  gap: 0.1rem;
  align-items: center;
  justify-content: center;
}

.pst-settings {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 18rem;
  max-width: 22rem;
  color: var(--pst-text);
}

.pst-settings__title {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--pst-text-muted);
}

.pst-settings__hint {
  font-size: 0.75rem;
  color: var(--pst-text-muted);
  margin-top: -0.35rem;
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
  color: var(--pst-text);
  cursor: pointer;
}

.pst-comment {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  color: var(--pst-text);
}

.pst-comment__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.4rem;
}

.pst-dialog {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  color: var(--pst-text);
}

.pst-dialog__mode {
  display: flex;
  gap: 0.5rem;
}

.pst-dialog .field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.pst-dialog .field > label {
  color: var(--pst-text);
  font-size: 0.85rem;
  font-weight: 600;
}

.pst-dialog__new-site {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.pst-dialog__section {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--pst-text-muted);
}

.pst-dialog__error {
  color: var(--p-red-500, #ef4444);
  font-size: 0.75rem;
}

.pst-dialog__hint {
  color: var(--pst-text-muted);
  font-size: 0.75rem;
}

.required {
  color: var(--p-red-500, #ef4444);
}
</style>

<!-- Teleported overlays inherit theme tokens from the document root -->
<style>
.pst-fluid-dialog.p-dialog {
  background: var(--layout-panel-bg);
  border-color: var(--layout-panel-border);
  color: var(--layout-text-color);
}

.pst-fluid-dialog.p-dialog .p-dialog-header {
  background: color-mix(in srgb, var(--layout-panel-bg) 92%, var(--layout-accent-soft));
  border-bottom-color: var(--layout-panel-border);
  color: var(--layout-text-color);
}

.pst-fluid-dialog.p-dialog .p-dialog-content {
  background: var(--layout-panel-bg);
  color: var(--layout-text-color);
}

.pst-settings-panel.p-popover,
.pst-comment-panel.p-popover {
  background: var(--layout-panel-bg);
  border-color: var(--layout-panel-border);
  color: var(--layout-text-color);
}

.pst-settings-panel.p-popover .p-popover-content,
.pst-comment-panel.p-popover .p-popover-content {
  background: transparent;
  color: inherit;
}
</style>
