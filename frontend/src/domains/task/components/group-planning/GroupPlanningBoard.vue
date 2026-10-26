<script setup>
import { computed, nextTick, onBeforeUnmount, provide, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import { useConfirm } from 'primevue/useconfirm'
import { useBreakpoint } from '@/domains/layout/composables/useBreakpoint'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { toApiDate } from '@/domains/shared/utils/dateUtils'
import { getGroupPlanning, saveGroupPlanning } from '@/domains/task/services/groupPlanningService'
import GroupPlanningCard from './GroupPlanningCard.vue'
import GroupPlanningPool from './GroupPlanningPool.vue'
import {
  copyImage,
  copyText,
  formatGroupPlanningText,
  memberLine,
  orderedMembers,
  planningDayLabel,
  planningHeading,
} from './groupPlanningFormat.js'

const props = defineProps({
  employees: { type: Array, default: () => [] },
  sites: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  reloadTick: { type: Number, default: 0 },
})

const toast = useAppToast()
const confirm = useConfirm()
const { isMobile } = useBreakpoint()
const { isAppMobile, bottomNavOffset } = useAppMobileLayout()

const planDate = ref(new Date())
const groups = ref([])
const pool = ref([])
const loading = ref(false)
const error = ref('')
const saveState = ref('idle')
const picked = ref(null)
const carriedFrom = ref('')
const dragKind = ref(null)
const selectedGroupId = ref(null)
provide('planningDragKind', dragKind)
const peopleOpen = ref(false)
const sitesOpen = ref(false)
const exportRoot = ref(null)
const copying = ref(false)

let hydrating = false
let silent = false
let saveTimer = null
let saveToken = 0
let loadToken = 0
let queuedDate = null
let queuedPayload = null

const compact = computed(() => isMobile.value)
const apiDate = computed(() => toApiDate(planDate.value))
const heading = computed(() => planningHeading(planDate.value))
const isToday = computed(() => apiDate.value === toApiDate(new Date()))
const assignedSiteIds = computed(() => groups.value.flatMap((group) => group.sites.map((site) => site.siteId)))
const pickedEmployeeId = computed(() => (picked.value?.kind === 'employee' ? picked.value.employee.id : null))
const pickedSiteId = computed(() => (picked.value?.kind === 'site' ? picked.value.site.id : null))
const carriedNote = computed(() => {
  if (!carriedFrom.value) return ''
  const label = planningDayLabel(carriedFrom.value)
  return label
    ? `Équipes reconduites depuis le ${label}, sans les sites.`
    : 'Équipes reconduites, sans les sites.'
})
const saveLabel = computed(() => {
  if (!props.canEdit) return 'Lecture seule'
  if (saveState.value === 'pending' || saveState.value === 'saving') return 'Enregistrement…'
  if (saveState.value === 'saved') return 'Enregistré'
  if (saveState.value === 'error') return 'Non enregistré'
  return ''
})
const dockStyle = computed(() => ({
  '--dock-bottom': isAppMobile.value && bottomNavOffset.value ? bottomNavOffset.value : '0.75rem',
}))

const groupDragOptions = computed(() => ({
  animation: 180,
  handle: '.group-card__handle',
  filter: '.group-zone, .no-drag',
  preventOnFilter: false,
  group: { name: 'planning-groups', pull: false, put: false },
  delay: compact.value ? 140 : 0,
  delayOnTouchOnly: true,
  forceFallback: true,
  fallbackOnBody: true,
  fallbackTolerance: 8,
  fallbackClass: 'planning-drag-fallback',
  ghostClass: 'planning-drag-ghost',
  disabled: !props.canEdit,
}))

function newId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID()
  return `grp-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function knownEmployee(id) {
  return props.employees.find((employee) => employee.id === id)
    || pool.value.find((employee) => employee.id === id)
    || { id, name: 'Personne retirée', mention: '', photoUrl: null, kind: 'employee' }
}

function knownSite(id) {
  return props.sites.find((site) => site.id === id) || { id, code: '', title: 'Site retiré' }
}

function applyDocument(rawGroups) {
  const used = new Set()
  groups.value = (rawGroups || []).map((group) => {
    const members = (group.memberIds || [])
      .filter((id) => typeof id === 'string' && id)
      .map((id) => {
        used.add(id)
        return { ...knownEmployee(id), id }
      })
    return {
      id: group.id || newId(),
      label: group.label || '',
      leaderEmployeeId: group.leaderEmployeeId || null,
      members,
      sites: (group.sites || [])
        .filter((site) => site?.siteId)
        .map((site) => {
          const known = knownSite(site.siteId)
          return {
            siteId: site.siteId,
            code: known.code || '',
            title: known.title || '',
            description: site.description || '',
          }
        }),
    }
  })
  pool.value = props.employees
    .filter((employee) => !used.has(employee.id))
    .map((employee) => ({ ...employee }))
    .sort((a, b) => a.name.localeCompare(b.name, 'fr'))
  if (!groups.value.some((group) => group.id === selectedGroupId.value)) {
    selectedGroupId.value = groups.value[0]?.id || null
  }
}

function serialize() {
  return groups.value.map((group) => ({
    id: group.id,
    label: String(group.label || '').trim() || null,
    leaderEmployeeId: group.members.some((member) => member.id === group.leaderEmployeeId)
      ? group.leaderEmployeeId
      : null,
    memberIds: group.members.map((member) => member.id),
    sites: group.sites.map((site) => ({
      siteId: site.siteId,
      description: String(site.description || '').trim() || null,
    })),
  }))
}

async function loadDay() {
  const token = ++loadToken
  const date = apiDate.value
  if (!date) return
  clearTimeout(saveTimer)
  saveToken += 1
  hydrating = true
  loading.value = true
  error.value = ''
  picked.value = null
  carriedFrom.value = ''
  try {
    const data = await getGroupPlanning(date)
    if (token !== loadToken) return
    applyDocument(data.groups || [])
    carriedFrom.value = data.carriedOver ? (data.carriedFrom || '') : ''
    saveState.value = 'idle'
  } catch (err) {
    if (token !== loadToken) return
    error.value = err.response?.data?.error || 'Impossible de charger le planning.'
    groups.value = []
    pool.value = []
  } finally {
    if (token === loadToken) {
      loading.value = false
      const shouldStoreCarry = Boolean(carriedFrom.value) && props.canEdit
      await nextTick()
      hydrating = false
      if (shouldStoreCarry) scheduleSave()
    }
  }
}

function scheduleSave() {
  if (hydrating || silent || !props.canEdit) return
  const token = ++saveToken
  queuedDate = apiDate.value
  queuedPayload = serialize()
  saveState.value = 'pending'
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    saveNow(token)
  }, 700)
}

async function saveNow(token) {
  if (!props.canEdit) return
  const date = apiDate.value
  const payload = serialize()
  saveState.value = 'saving'
  try {
    await saveGroupPlanning(date, payload)
    if (token !== saveToken) return
    saveState.value = 'saved'
  } catch (err) {
    if (token !== saveToken) return
    saveState.value = 'error'
    toast.add({
      severity: 'error',
      summary: 'Planning',
      detail: err.response?.data?.error || 'Enregistrement impossible.',
    })
  }
}

watch(groups, scheduleSave, { deep: true })

watch(apiDate, (next, previous) => {
  if (!next || next === previous) return
  if (
    previous
    && props.canEdit
    && queuedDate === previous
    && queuedPayload
    && (saveState.value === 'pending' || saveState.value === 'saving')
  ) {
    const payload = queuedPayload
    clearTimeout(saveTimer)
    saveToken += 1
    saveGroupPlanning(previous, payload).catch(() => {})
  }
  queuedPayload = null
  loadDay()
}, { immediate: true })

watch(() => props.reloadTick, (tick) => {
  if (tick) loadDay()
})

watch(() => props.employees, () => {
  if (hydrating || loading.value) return
  silent = true
  const used = new Set()
  for (const group of groups.value) {
    group.members = group.members.map((member) => {
      used.add(member.id)
      const fresh = props.employees.find((employee) => employee.id === member.id)
      return fresh ? { ...fresh } : member
    })
  }
  pool.value = props.employees
    .filter((employee) => !used.has(employee.id))
    .map((employee) => ({ ...employee }))
    .sort((a, b) => a.name.localeCompare(b.name, 'fr'))
  nextTick(() => {
    silent = false
  })
}, { deep: true })

watch(() => props.sites, () => {
  if (hydrating) return
  silent = true
  for (const group of groups.value) {
    group.sites = group.sites.map((site) => {
      const fresh = props.sites.find((item) => item.id === site.siteId)
      if (!fresh) return site
      return { ...site, code: fresh.code || '', title: fresh.title || '' }
    })
  }
  nextTick(() => {
    silent = false
  })
}, { deep: true })

function shift(days) {
  const next = new Date(planDate.value)
  next.setDate(next.getDate() + days)
  planDate.value = next
}

function goToday() {
  planDate.value = new Date()
}

function addGroup() {
  const group = {
    id: newId(),
    label: '',
    leaderEmployeeId: null,
    members: [],
    sites: [],
  }
  groups.value = [...groups.value, group]
  selectedGroupId.value = group.id
}

function removeGroup(group) {
  confirm.require({
    header: 'Supprimer le groupe',
    message: 'Les personnes retournent dans l’équipe disponible.',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => {
      pool.value = [...pool.value, ...group.members].sort((a, b) => a.name.localeCompare(b.name, 'fr'))
      groups.value = groups.value.filter((item) => item.id !== group.id)
      if (selectedGroupId.value === group.id) selectedGroupId.value = groups.value[0]?.id || null
    },
  })
}

function moveGroup(index, delta) {
  const next = index + delta
  if (next < 0 || next >= groups.value.length) return
  const copy = groups.value.slice()
  const [item] = copy.splice(index, 1)
  copy.splice(next, 0, item)
  groups.value = copy
}

function togglePickEmployee(employee) {
  if (!props.canEdit) return
  if (picked.value?.kind === 'employee' && picked.value.employee.id === employee.id) {
    picked.value = null
    return
  }
  picked.value = { kind: 'employee', employee }
}

function togglePickSite(site) {
  if (!props.canEdit) return
  if (picked.value?.kind === 'site' && picked.value.site.id === site.id) {
    picked.value = null
    return
  }
  picked.value = { kind: 'site', site }
}

function targetGroup() {
  return groups.value.find((group) => group.id === selectedGroupId.value) || null
}

function takeEmployee(id) {
  pool.value = pool.value.filter((employee) => employee.id !== id)
  for (const group of groups.value) {
    if (!group.members.some((member) => member.id === id)) continue
    group.members = group.members.filter((member) => member.id !== id)
    if (group.leaderEmployeeId === id) group.leaderEmployeeId = null
  }
}

function addSiteTo(group, site) {
  const siteId = site.siteId || site.id
  if (groups.value.some((item) => item.sites.some((entry) => entry.siteId === siteId))) {
    toast.add({ severity: 'warn', summary: 'Site', detail: 'Ce site est déjà affecté à un groupe.' })
    return false
  }
  group.sites.push({
    siteId,
    code: site.code || '',
    title: site.title || '',
    description: '',
  })
  return true
}

function requireTarget() {
  const target = targetGroup()
  if (target) return target
  toast.add({
    severity: 'info',
    summary: 'Groupe',
    detail: groups.value.length ? 'Choisissez d’abord le groupe cible.' : 'Créez d’abord un groupe.',
  })
  return null
}

function placePerson(employee) {
  if (!props.canEdit) return
  const target = requireTarget()
  if (!target) return
  takeEmployee(employee.id)
  target.members.push(employee)
  picked.value = null
  peopleOpen.value = false
}

function placeSite(site) {
  if (!props.canEdit) return
  const target = requireTarget()
  if (!target) return
  if (!addSiteTo(target, site)) return
  picked.value = null
  sitesOpen.value = false
}

function onReturnMember(member) {
  if (!pool.value.some((employee) => employee.id === member.id)) {
    pool.value = [...pool.value, member].sort((a, b) => a.name.localeCompare(b.name, 'fr'))
  }
}

function onSiteAdded(group, payload) {
  const added = group.sites[payload.index]
  if (!added) return
  const elsewhere = groups.value.some((item) => item.id !== group.id && item.sites.some((site) => site.siteId === payload.siteId))
  const localDup = group.sites.filter((site) => site.siteId === payload.siteId).length > 1
  if (elsewhere || localDup) {
    group.sites.splice(payload.index, 1)
    toast.add({ severity: 'warn', summary: 'Site', detail: 'Ce site est déjà affecté à un groupe.' })
  }
}

async function exportText() {
  try {
    await copyText(formatGroupPlanningText(planDate.value, groups.value))
    toast.add({ severity: 'success', summary: 'Planning', detail: 'Texte copié.' })
  } catch {
    toast.add({ severity: 'error', summary: 'Planning', detail: 'Copie du texte impossible.' })
  }
}

async function exportImage() {
  if (!exportRoot.value || copying.value) return
  copying.value = true
  try {
    const mode = await copyImage(exportRoot.value, `planning-groupe-${apiDate.value}.png`)
    toast.add({
      severity: 'success',
      summary: 'Planning',
      detail: mode === 'clipboard' ? 'Image copiée.' : 'Image téléchargée.',
    })
  } catch {
    toast.add({ severity: 'error', summary: 'Planning', detail: 'Export de l’image impossible.' })
  } finally {
    copying.value = false
  }
}

onBeforeUnmount(() => {
  clearTimeout(saveTimer)
  if (saveState.value === 'pending' && props.canEdit) {
    saveGroupPlanning(apiDate.value, serialize()).catch(() => {})
  }
})
</script>

<template>
  <div class="group-planning" :class="{ 'is-compact': compact }">
    <div class="group-planning__toolbar">
      <div class="group-planning__date">
        <Button icon="pi pi-chevron-left" text rounded aria-label="Jour précédent" @click="shift(-1)" />
        <DatePicker
          v-model="planDate"
          class="group-planning__picker"
          date-format="dd/mm/yy"
          :manual-input="false"
          append-to="body"
          aria-label="Date du planning"
        />
        <Button icon="pi pi-chevron-right" text rounded aria-label="Jour suivant" @click="shift(1)" />
        <Button v-if="!isToday" label="Aujourd’hui" text size="small" @click="goToday" />
      </div>
      <div class="group-planning__toolbar-actions">
        <span v-if="saveLabel" class="group-planning__status">{{ saveLabel }}</span>
        <Button
          :label="compact ? undefined : 'Nouveau groupe'"
          icon="pi pi-plus"
          size="small"
          aria-label="Nouveau groupe"
          :disabled="!canEdit"
          @click="addGroup"
        />
        <Button
          :label="compact ? undefined : 'Texte'"
          icon="pi pi-copy"
          size="small"
          severity="secondary"
          aria-label="Copier le texte"
          @click="exportText"
        />
        <Button
          :label="compact ? undefined : 'Image'"
          icon="pi pi-image"
          size="small"
          severity="secondary"
          aria-label="Copier l’image"
          :loading="copying"
          @click="exportImage"
        />
      </div>
    </div>

    <p v-if="carriedNote" class="group-planning__note">{{ carriedNote }}</p>

    <p v-if="error" class="group-planning__error">
      {{ error }}
      <Button label="Réessayer" text size="small" @click="loadDay" />
    </p>
    <p v-else-if="loading" class="group-planning__status">Chargement du jour…</p>

    <div v-else class="group-planning__layout">
      <aside v-if="!compact" class="group-planning__side">
        <GroupPlanningPool
          v-model:pool="pool"
          :sites="sites"
          :assigned-site-ids="assignedSiteIds"
          :can-edit="canEdit"
          :picked-employee-id="pickedEmployeeId"
          :picked-site-id="pickedSiteId"
          @pick-employee="togglePickEmployee"
          @pick-site="togglePickSite"
          @place-person="placePerson"
          @place-site="placeSite"
        />
      </aside>

      <div class="group-planning__board">
        <draggable
          v-model="groups"
          item-key="id"
          class="group-planning__groups"
          v-bind="groupDragOptions"
          @start="dragKind = 'group'"
          @end="dragKind = null"
        >
          <template #item="{ element, index }">
            <GroupPlanningCard
              :group="element"
              :index="index"
              :can-edit="canEdit"
              :compact="compact"
              :selected="selectedGroupId === element.id"
              :can-move-up="index > 0"
              :can-move-down="index < groups.length - 1"
              @select="selectedGroupId = element.id"
              @remove="removeGroup(element)"
              @return-member="onReturnMember"
              @site-added="onSiteAdded(element, $event)"
              @move="moveGroup(index, $event)"
            />
          </template>
        </draggable>
        <div v-if="!groups.length" class="group-planning__empty">
          <p>Aucun groupe pour cette journée.</p>
          <Button label="Nouveau groupe" icon="pi pi-plus" :disabled="!canEdit" @click="addGroup" />
        </div>
      </div>
    </div>

    <div v-if="compact" class="group-planning__dock" :style="dockStyle">
      <Button label="Personnes" icon="pi pi-users" size="small" severity="secondary" @click="peopleOpen = true" />
      <Button label="Sites" icon="pi pi-map-marker" size="small" severity="secondary" @click="sitesOpen = true" />
    </div>

    <Drawer
      v-model:visible="peopleOpen"
      position="bottom"
      header="Personnes disponibles"
      class="group-planning-drawer"
      :modal="false"
      dismissable
      style="height: min(52vh, 28rem)"
    >
      <GroupPlanningPool
        v-if="peopleOpen"
        v-model:pool="pool"
        section="people"
        compact
        :sites="sites"
        :assigned-site-ids="assignedSiteIds"
        :can-edit="canEdit"
        :picked-employee-id="pickedEmployeeId"
        :picked-site-id="pickedSiteId"
        @pick-employee="togglePickEmployee"
        @pick-site="togglePickSite"
        @place-person="placePerson"
        @place-site="placeSite"
      />
    </Drawer>
    <Drawer
      v-model:visible="sitesOpen"
      position="bottom"
      header="Sites"
      class="group-planning-drawer"
      :modal="false"
      dismissable
      style="height: min(52vh, 28rem)"
    >
      <GroupPlanningPool
        v-if="sitesOpen"
        v-model:pool="pool"
        section="sites"
        compact
        :sites="sites"
        :assigned-site-ids="assignedSiteIds"
        :can-edit="canEdit"
        :picked-employee-id="pickedEmployeeId"
        :picked-site-id="pickedSiteId"
        @pick-employee="togglePickEmployee"
        @pick-site="togglePickSite"
        @place-person="placePerson"
        @place-site="placeSite"
      />
    </Drawer>

    <div class="group-planning__export-host" aria-hidden="true">
      <article ref="exportRoot" class="export-sheet">
        <h1>{{ heading }}</h1>
        <section v-for="(group, index) in groups" :key="group.id" class="export-sheet__group">
          <h2>{{ group.label ? `Groupe ${index + 1} — ${group.label}` : `Groupe ${index + 1}` }}</h2>
          <p v-for="site in group.sites" :key="site.siteId" class="export-sheet__site">
            <span>{{ [site.code, site.title].filter(Boolean).join(' ') }}</span>
            <em v-if="site.description">Tâche : {{ site.description }}</em>
          </p>
          <p
            v-for="member in orderedMembers(group)"
            :key="member.id"
            class="export-sheet__member"
          >
            {{ memberLine(member, member.id === group.leaderEmployeeId) }}
          </p>
        </section>
      </article>
    </div>
  </div>
</template>

<style scoped>
.group-planning {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  min-width: 0;
}

.group-planning__toolbar,
.group-planning__date,
.group-planning__toolbar-actions,
.group-planning__dock {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.group-planning__toolbar {
  justify-content: space-between;
  padding: 0.45rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: var(--layout-radius-lg, 0.9rem);
  background: color-mix(in srgb, var(--layout-panel-bg) 92%, var(--layout-accent-soft));
  box-shadow: var(--layout-shadow-panel);
}

.group-planning__picker {
  width: 9.5rem;
}

.group-planning__status,
.group-planning__note,
.group-planning__error,
.group-planning__empty p {
  margin: 0;
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.85rem;
}

.group-planning__note {
  padding: 0.55rem 0.75rem;
  border: 1px solid color-mix(in srgb, var(--layout-accent) 28%, var(--layout-panel-border));
  border-radius: var(--layout-radius-lg, 0.85rem);
  background: color-mix(in srgb, var(--layout-accent-soft) 62%, var(--layout-panel-bg));
  color: var(--layout-text-color);
}

.group-planning__error {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.group-planning__error {
  color: var(--p-red-500, #dc2626);
}

.group-planning__layout {
  display: grid;
  grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
}

.group-planning.is-compact .group-planning__layout {
  grid-template-columns: 1fr;
}

.group-planning__side {
  position: sticky;
  top: 0.5rem;
  display: flex;
  max-height: calc(100vh - 8rem);
  min-height: 0;
}

.group-planning__groups {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 4rem;
}

.group-planning__empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.6rem;
  padding: 0.5rem 0 1rem;
}

.group-planning.is-compact {
  padding-bottom: 4.75rem;
}

.group-planning__dock {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  bottom: var(--dock-bottom, 0.75rem);
  z-index: 12;
  padding: 0.45rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: 999px;
  background: color-mix(in srgb, var(--layout-panel-bg) 94%, transparent);
  backdrop-filter: blur(10px);
  width: max-content;
  max-width: calc(100vw - 1.5rem);
}

.group-planning__dock :deep(.p-button) {
  min-height: 2.75rem;
}

.group-planning__export-host {
  position: fixed;
  left: 0;
  top: 0;
  transform: translateX(-120vw);
  pointer-events: none;
}

.export-sheet {
  width: 480px;
  padding: 28px 32px 36px;
  background: #ffffff;
  color: #0f172a;
  font-family: "Segoe UI", sans-serif;
}

.export-sheet h1 {
  margin: 0 0 18px;
  font-size: 22px;
  line-height: 1.3;
}

.export-sheet__group + .export-sheet__group {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #e2e8f0;
}

.export-sheet h2 {
  margin: 0 0 8px;
  font-size: 18px;
  color: #1d4ed8;
}

.export-sheet p {
  margin: 0;
  font-size: 16px;
  line-height: 1.45;
}

.export-sheet__site {
  display: flex;
  flex-direction: column;
  margin-top: 4px;
}

.export-sheet__site em {
  color: #334155;
  font-style: normal;
}

.export-sheet__member {
  margin-top: 2px;
}
</style>

<style>
.planning-drag-fallback {
  z-index: 10000;
  opacity: 0.96;
  pointer-events: none;
}

.planning-drag-ghost {
  opacity: 0.45;
}
</style>
