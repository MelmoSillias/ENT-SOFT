<script setup>
import { computed, ref, watch } from 'vue'
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

const emit = defineEmits(['pick-employee', 'pick-site', 'add-site'])

const peopleQuery = ref('')
const siteQuery = ref('')
const siteDragItems = ref([])

const dragOptions = computed(() => ({
  animation: 160,
  delay: props.compact ? 180 : 0,
  delayOnTouchOnly: true,
  touchStartThreshold: 4,
  forceFallback: true,
  fallbackOnBody: true,
  fallbackTolerance: 6,
  ghostClass: 'is-ghost',
  filter: '.no-drag',
  preventOnFilter: false,
  disabled: !props.canEdit,
}))

const visiblePeople = computed(() => {
  const query = peopleQuery.value.trim().toLowerCase()
  if (!query) return null
  return new Set(
    pool.value
      .filter((person) => `${person.name} ${person.mention || ''}`.toLowerCase().includes(query))
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
  siteDragItems.value = list.map((site) => ({ ...site }))
}, { immediate: true })

const dragging = ref(false)

function beginDrag() {
  dragging.value = true
}

function endDrag() {
  setTimeout(() => {
    dragging.value = false
  }, 80)
}

function pickEmployee(employee) {
  if (dragging.value) return
  emit('pick-employee', employee)
}

function pickSite(site) {
  if (dragging.value) return
  emit('pick-site', site)
}

function matchesPerson(person) {
  return !visiblePeople.value || visiblePeople.value.has(person.id)
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
    <section v-if="section !== 'sites'" class="planning-pool__block">
      <header class="planning-pool__heading">
        <h3>Équipe</h3>
        <span>{{ pool.length }}</span>
      </header>
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="peopleQuery" placeholder="Rechercher une personne" fluid />
      </IconField>
      <draggable
        v-model="pool"
        item-key="id"
        class="planning-pool__people"
        :group="{ name: 'planning-people', pull: true, put: true }"
        v-bind="dragOptions"
        @start="beginDrag"
        @end="endDrag"
      >
        <template #item="{ element }">
          <div
            v-show="matchesPerson(element)"
            class="person-chip"
            :class="{ 'is-picked': pickedEmployeeId === element.id }"
          >
            <AppPersonAvatar
              :name="element.name"
              :photo-url="element.photoUrl"
              size="small"
              :previewable="false"
            />
            <button type="button" class="person-chip__body" @click="pickEmployee(element)">
              <span>{{ element.name }}</span>
              <small v-if="element.mention">{{ element.mention }}</small>
            </button>
          </div>
        </template>
      </draggable>
      <p v-if="!pool.length" class="planning-pool__empty">Toute l’équipe est affectée.</p>
      <p v-else-if="visiblePeople && !pool.some(matchesPerson)" class="planning-pool__empty">Aucun résultat.</p>
    </section>

    <section v-if="section !== 'people'" class="planning-pool__block">
      <header class="planning-pool__heading">
        <h3>Sites</h3>
      </header>
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="siteQuery" placeholder="Code ou nom du site" fluid />
      </IconField>
      <draggable
        v-model="siteDragItems"
        item-key="id"
        class="planning-pool__sites"
        :group="{ name: 'planning-sites', pull: 'clone', put: false }"
        :sort="false"
        :clone="cloneSite"
        v-bind="dragOptions"
        @start="beginDrag"
        @end="endDrag"
      >
        <template #item="{ element }">
          <div class="site-chip" :class="{ 'is-picked': pickedSiteId === element.id }">
            <button type="button" class="site-chip__body" @click="pickSite(element)">
              <strong>{{ element.code }}</strong>
              <span>{{ element.title }}</span>
            </button>
            <Button
              class="no-drag"
              icon="pi pi-plus"
              text
              rounded
              severity="secondary"
              aria-label="Ajouter au groupe sélectionné"
              :disabled="!canEdit"
              @click="emit('add-site', element)"
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
  gap: 1rem;
  min-width: 0;
}

.planning-pool__block {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  min-width: 0;
}

.planning-pool__heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
}

.planning-pool__heading h3 {
  margin: 0;
  font-size: 0.95rem;
}

.planning-pool__heading span,
.planning-pool__empty {
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.8rem;
}

.planning-pool__empty {
  margin: 0;
}

.planning-pool__people,
.planning-pool__sites {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-height: 2.5rem;
  max-height: 22rem;
  overflow: auto;
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

.person-chip.is-picked,
.site-chip.is-picked {
  border-color: var(--p-primary-color);
  box-shadow: inset 0 0 0 1px var(--p-primary-color);
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
.site-chip__body span {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.person-chip__body small,
.site-chip__body strong {
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.75rem;
}

.site-chip__body {
  flex-direction: row;
  align-items: baseline;
  gap: 0.4rem;
}

.site-chip__body strong {
  color: inherit;
  font-size: 0.85rem;
}
</style>
