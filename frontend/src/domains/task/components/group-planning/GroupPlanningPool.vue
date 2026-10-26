<script setup>
import { computed, inject, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'

const pool = defineModel('pool', { type: Array, required: true })

const props = defineProps({
  sites: { type: Array, default: () => [] },
  assignedSiteIds: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  pickedEmployeeId: { type: String, default: null },
  pickedSiteId: { type: String, default: null },
  compact: { type: Boolean, default: false },
  section: { type: String, default: 'all' },
})

const emit = defineEmits(['pick-employee', 'pick-site', 'place-person', 'place-site'])

const dragKind = inject('planningDragKind', null)
const peopleQuery = ref('')
const siteQuery = ref('')
const siteDragItems = ref([])
const dragging = ref(false)

const dragOptions = computed(() => ({
  animation: 160,
  delay: props.compact ? 180 : 0,
  delayOnTouchOnly: true,
  touchStartThreshold: 4,
  forceFallback: true,
  fallbackOnBody: true,
  fallbackTolerance: 8,
  fallbackClass: 'planning-drag-fallback',
  ghostClass: 'planning-drag-ghost',
  emptyInsertThreshold: 48,
  filter: '.no-drag',
  preventOnFilter: false,
  disabled: !props.canEdit,
}))

const peopleGroup = {
  name: 'planning-people',
  pull: true,
  put: (_to, _from, dragEl) => dragEl?.dataset?.planningKind === 'person',
}

const siteGroup = {
  name: 'planning-sites',
  pull: 'clone',
  put: false,
}

const visiblePeople = computed(() => {
  const query = peopleQuery.value.trim().toLowerCase()
  if (!query) return null
  return new Set(
    pool.value
      .filter((person) => `${person.name} ${person.mention || ''} ${person.phone || ''} ${person.kind === 'prestataire' ? 'prestataire' : 'employé'}`.toLowerCase().includes(query))
      .map((person) => person.id),
  )
})

const availableSites = computed(() => {
  const taken = new Set(props.assignedSiteIds)
  const query = siteQuery.value.trim().toLowerCase()
  return props.sites
    .filter((site) => !taken.has(site.id))
    .filter((site) => !query || `${site.code} ${site.title}`.toLowerCase().includes(query))
    .slice(0, 40)
})

watch(availableSites, (list) => {
  if (dragging.value) return
  siteDragItems.value = list.map((site) => ({ ...site }))
}, { immediate: true })

const peopleZoneClass = computed(() => zoneClass('person'))
const siteZoneClass = computed(() => {
  const current = dragKind?.value
  if (!current || current === 'group') return {}
  return { 'is-blocked': current === 'person' }
})

function zoneClass(kind) {
  const current = dragKind?.value
  if (!current || current === 'group') return {}
  return {
    'is-target': current === kind,
    'is-blocked': current !== kind,
  }
}

function beginDrag(kind) {
  dragging.value = true
  if (dragKind) dragKind.value = kind
}

function endDrag() {
  const kind = dragKind?.value
  setTimeout(() => {
    dragging.value = false
    if (dragKind && dragKind.value === kind) dragKind.value = null
    siteDragItems.value = availableSites.value.map((site) => ({ ...site }))
  }, 80)
}

function allowPerson(event) {
  return event.dragged?.dataset?.planningKind === 'person'
}

function pickEmployee(employee) {
  if (dragging.value || !props.canEdit) return
  emit('pick-employee', employee)
}

function pickSite(site) {
  if (dragging.value || !props.canEdit) return
  emit('pick-site', site)
}

function matchesPerson(person) {
  return !visiblePeople.value || visiblePeople.value.has(person.id)
}

function personRole(person) {
  if (person.kind === 'prestataire') return 'Prestataire'
  return person.mention || ''
}

function cloneSite(site) {
  return {
    siteId: site.id,
    code: site.code,
    title: site.title,
    description: '',
  }
}
</script>

<template>
  <div class="planning-pool">
    <section v-if="section !== 'sites'" class="planning-pool__block group-zone group-zone--people" :class="peopleZoneClass">
      <header class="planning-pool__heading">
        <h3><i class="pi pi-users" /> Personnes</h3>
        <span>{{ pool.length }}</span>
      </header>
      <IconField class="no-drag">
        <InputIcon class="pi pi-search" />
        <InputText v-model="peopleQuery" placeholder="Employé ou prestataire" fluid />
      </IconField>
      <draggable
        v-model="pool"
        item-key="id"
        class="planning-pool__list"
        draggable=".person-chip:not(.is-hidden)"
        :group="peopleGroup"
        :move="allowPerson"
        v-bind="dragOptions"
        @start="beginDrag('person')"
        @end="endDrag"
      >
        <template #item="{ element }">
          <div
            class="person-chip"
            :class="{ 'is-picked': pickedEmployeeId === element.id, 'is-hidden': !matchesPerson(element) }"
            data-planning-kind="person"
          >
            <AppPersonAvatar
              :name="element.name"
              :photo-url="element.photoUrl"
              :kind="element.kind || 'employee'"
              size="small"
              :previewable="false"
            />
            <button type="button" class="person-chip__body" @click="pickEmployee(element)">
              <span>{{ element.name }}</span>
              <small v-if="personRole(element)">{{ personRole(element) }}</small>
              <small v-if="element.phone" class="person-chip__phone"><i class="pi pi-phone" /> {{ element.phone }}</small>
            </button>
            <Button
              v-if="pickedEmployeeId === element.id"
              class="no-drag"
              label="Placer"
              size="small"
              @click.stop="emit('place-person', element)"
            />
          </div>
        </template>
      </draggable>
      <p v-if="!pool.length" class="planning-pool__empty">Toute l’équipe est affectée.</p>
      <p v-else-if="visiblePeople && !pool.some(matchesPerson)" class="planning-pool__empty">Aucun résultat.</p>
    </section>

    <section v-if="section !== 'people'" class="planning-pool__block group-zone group-zone--sites" :class="siteZoneClass">
      <header class="planning-pool__heading">
        <h3><i class="pi pi-map-marker" /> Sites</h3>
      </header>
      <IconField class="no-drag">
        <InputIcon class="pi pi-search" />
        <InputText v-model="siteQuery" placeholder="Code ou nom du site" fluid />
      </IconField>
      <draggable
        v-model="siteDragItems"
        item-key="id"
        class="planning-pool__list"
        draggable=".site-chip"
        :group="siteGroup"
        :sort="false"
        :clone="cloneSite"
        v-bind="dragOptions"
        @start="beginDrag('site')"
        @end="endDrag"
      >
        <template #item="{ element }">
          <div class="site-chip" :class="{ 'is-picked': pickedSiteId === element.id }" data-planning-kind="site">
            <button type="button" class="site-chip__body" @click="pickSite(element)">
              <strong>{{ element.code }}</strong>
              <span>{{ element.title }}</span>
            </button>
            <Button
              v-if="pickedSiteId === element.id"
              class="no-drag"
              label="Placer"
              size="small"
              @click.stop="emit('place-site', element)"
            />
          </div>
        </template>
      </draggable>
      <p v-if="!siteDragItems.length" class="planning-pool__empty">Aucun site à ajouter.</p>
    </section>
  </div>
</template>

<style scoped>
.planning-pool {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
  width: 100%;
  min-height: 0;
}

.planning-pool__block {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  min-width: 0;
  min-height: 0;
  padding: 0.7rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: var(--layout-radius-lg, 0.9rem);
  box-shadow: var(--layout-shadow-panel);
}

.group-zone--sites {
  background: color-mix(in srgb, var(--layout-accent-soft) 48%, var(--layout-panel-bg));
}

.group-zone--people {
  background: color-mix(in srgb, var(--layout-panel-bg) 88%, var(--layout-page-bg, var(--layout-accent-soft)));
}

.planning-pool__block.is-target {
  border-color: var(--layout-accent);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--layout-accent) 40%, transparent), var(--layout-shadow-panel);
}

.planning-pool__block.is-blocked {
  opacity: 0.5;
}

.planning-pool__heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
}

.planning-pool__heading h3 {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  font-size: 0.92rem;
}

.planning-pool__heading span,
.planning-pool__empty {
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.8rem;
}

.planning-pool__empty {
  margin: 0;
}

.planning-pool__list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-height: 4.5rem;
  max-height: 22rem;
  overflow: auto;
  padding-right: 0.1rem;
}

.person-chip,
.site-chip {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.35rem 0.45rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: 0.75rem;
  background: var(--layout-panel-bg);
  cursor: grab;
  min-height: 2.75rem;
}

.person-chip.is-hidden {
  display: none;
}

.person-chip.is-picked,
.site-chip.is-picked {
  border-color: color-mix(in srgb, var(--layout-accent) 65%, var(--layout-panel-border));
  background: color-mix(in srgb, var(--layout-accent-soft) 55%, var(--layout-panel-bg));
}

.person-chip__body,
.site-chip__body {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.05rem;
  flex: 1;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.person-chip__body span,
.person-chip__body small,
.site-chip__body span,
.site-chip__body strong {
  max-width: 100%;
  white-space: normal;
  overflow-wrap: anywhere;
}

.person-chip__phone {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.person-chip__body small,
.site-chip__body strong {
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.75rem;
}

.site-chip__body {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 0.4rem;
}

.site-chip__body strong {
  color: inherit;
  font-size: 0.85rem;
}
</style>
