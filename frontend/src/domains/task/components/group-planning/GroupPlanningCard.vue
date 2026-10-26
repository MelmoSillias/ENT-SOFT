<script setup>
import { computed, inject } from 'vue'
import draggable from 'vuedraggable'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'

const props = defineProps({
  group: { type: Object, required: true },
  index: { type: Number, required: true },
  canEdit: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
  canMoveUp: { type: Boolean, default: false },
  canMoveDown: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'remove', 'return-member', 'site-added', 'move'])

const dragKind = inject('planningDragKind', null)

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
  emptyInsertThreshold: 56,
  filter: '.no-drag',
  preventOnFilter: false,
  disabled: !props.canEdit,
}))

const siteGroup = {
  name: 'planning-sites',
  pull: true,
  put: (_to, _from, dragEl) => dragEl?.dataset?.planningKind === 'site',
}

const peopleGroup = {
  name: 'planning-people',
  pull: true,
  put: (_to, _from, dragEl) => dragEl?.dataset?.planningKind === 'person',
}

const siteZoneClass = computed(() => zoneClass('site'))
const peopleZoneClass = computed(() => zoneClass('person'))

function zoneClass(kind) {
  const current = dragKind?.value
  if (!current || current === 'group') return {}
  return {
    'is-target': current === kind,
    'is-blocked': current !== kind,
  }
}

function beginDrag(kind) {
  if (dragKind) dragKind.value = kind
}

function endDrag() {
  if (dragKind) dragKind.value = null
}

function allowKind(kind) {
  return (event) => event.dragged?.dataset?.planningKind === kind
}

function isLeader(member) {
  return props.group.leaderEmployeeId === member.id
}

function toggleLeader(member) {
  props.group.leaderEmployeeId = isLeader(member) ? null : member.id
}

function syncLeader() {
  if (!props.group.members.some((member) => member.id === props.group.leaderEmployeeId)) {
    props.group.leaderEmployeeId = null
  }
}

function removeMember(member) {
  props.group.members = props.group.members.filter((item) => item.id !== member.id)
  if (props.group.leaderEmployeeId === member.id) props.group.leaderEmployeeId = null
  emit('return-member', member)
}

function removeSite(index) {
  props.group.sites.splice(index, 1)
}

function onSiteAdd(event) {
  const added = props.group.sites[event.newIndex]
  if (!added?.siteId) {
    props.group.sites.splice(event.newIndex, 1)
    return
  }
  emit('site-added', { siteId: added.siteId, index: event.newIndex })
}

function onMemberAdd(event) {
  const added = props.group.members[event.newIndex]
  if (!added?.id || added.siteId) {
    props.group.members.splice(event.newIndex, 1)
    return
  }
  syncLeader()
}

function personRole(member) {
  if (member.kind === 'prestataire') return 'Prestataire'
  return member.mention || ''
}
</script>

<template>
  <article class="group-card" :class="{ 'is-selected': selected, 'is-compact': compact }" @click="emit('select')">
    <header class="group-card__header">
      <button type="button" class="group-card__handle" aria-label="Déplacer le groupe" :disabled="!canEdit">
        <i class="pi pi-bars" />
      </button>
      <div class="group-card__identity">
        <span class="group-card__title">Groupe {{ index + 1 }}</span>
        <span v-if="selected" class="group-card__target">Cible</span>
      </div>
      <div class="group-card__moves no-drag">
        <Button
          icon="pi pi-chevron-up"
          text
          rounded
          severity="secondary"
          aria-label="Monter le groupe"
          :disabled="!canEdit || !canMoveUp"
          @click="emit('move', -1)"
        />
        <Button
          icon="pi pi-chevron-down"
          text
          rounded
          severity="secondary"
          aria-label="Descendre le groupe"
          :disabled="!canEdit || !canMoveDown"
          @click="emit('move', 1)"
        />
      </div>
      <InputText
        v-model="group.label"
        class="group-card__label no-drag"
        placeholder="Libellé, ex. installation AAU"
        :disabled="!canEdit"
        @click.stop
      />
      <Button
        class="group-card__remove no-drag"
        icon="pi pi-trash"
        text
        rounded
        severity="danger"
        aria-label="Supprimer le groupe"
        :disabled="!canEdit"
        @click.stop="emit('remove')"
      />
    </header>

    <section class="group-zone group-zone--sites" :class="siteZoneClass">
      <header class="group-zone__head">
        <span class="group-zone__title"><i class="pi pi-map-marker" /> Sites</span>
        <span class="group-zone__count">{{ group.sites.length }}</span>
      </header>
      <div class="group-zone__body">
        <draggable
          v-model="group.sites"
          item-key="siteId"
          class="group-zone__list"
          draggable=".site-row"
          :group="siteGroup"
          :move="allowKind('site')"
          v-bind="dragOptions"
          @start="beginDrag('site')"
          @end="endDrag"
          @add="onSiteAdd"
        >
          <template #item="{ element, index: siteIndex }">
            <div class="site-row" data-planning-kind="site">
              <div class="site-row__head">
                <div class="site-row__title">
                  <strong>{{ element.code }}</strong>
                  <span>{{ element.title }}</span>
                </div>
                <Button
                  class="site-row__remove no-drag"
                  icon="pi pi-times"
                  text
                  rounded
                  severity="secondary"
                  aria-label="Retirer le site"
                  :disabled="!canEdit"
                  @click.stop="removeSite(siteIndex)"
                />
              </div>
              <Textarea
                v-model="element.description"
                class="site-row__task no-drag"
                placeholder="Tâche sur ce site"
                rows="1"
                auto-resize
                :disabled="!canEdit"
                @click.stop
              />
            </div>
          </template>
        </draggable>
        <p v-if="!group.sites.length" class="group-zone__empty">Déposez un site dans cette zone.</p>
      </div>
    </section>

    <section class="group-zone group-zone--people" :class="peopleZoneClass">
      <header class="group-zone__head">
        <span class="group-zone__title"><i class="pi pi-users" /> Personnes</span>
        <span class="group-zone__count">{{ group.members.length }}</span>
      </header>
      <div class="group-zone__body">
        <draggable
          v-model="group.members"
          item-key="id"
          class="group-zone__list"
          draggable=".member-row"
          :group="peopleGroup"
          :move="allowKind('person')"
          v-bind="dragOptions"
          @start="beginDrag('person')"
          @end="endDrag"
          @add="onMemberAdd"
          @remove="syncLeader"
        >
          <template #item="{ element }">
            <div class="member-row" data-planning-kind="person" :class="{ 'is-leader': isLeader(element) }">
              <AppPersonAvatar
                class="no-drag"
                :name="element.name"
                :photo-url="element.photoUrl"
                :kind="element.kind || 'employee'"
                size="small"
                :previewable="false"
              />
              <div class="member-row__text">
                <span>{{ element.name }}</span>
                <small v-if="personRole(element)">{{ personRole(element) }}</small>
                <small v-if="element.phone" class="member-row__phone"><i class="pi pi-phone" /> {{ element.phone }}</small>
              </div>
              <Button
                class="no-drag"
                :icon="isLeader(element) ? 'pi pi-star-fill' : 'pi pi-star'"
                text
                rounded
                :severity="isLeader(element) ? 'warn' : 'secondary'"
                :aria-pressed="isLeader(element)"
                aria-label="Chef d’équipe"
                :disabled="!canEdit"
                @click.stop="toggleLeader(element)"
              />
              <Button
                class="no-drag"
                icon="pi pi-times"
                text
                rounded
                severity="secondary"
                aria-label="Retirer du groupe"
                :disabled="!canEdit"
                @click.stop="removeMember(element)"
              />
            </div>
          </template>
        </draggable>
        <p v-if="!group.members.length" class="group-zone__empty">Déposez des employés ou des prestataires ici.</p>
      </div>
      <p v-if="group.members.length && !group.leaderEmployeeId" class="group-zone__hint">Désignez le chef avec l’étoile.</p>
    </section>
  </article>
</template>

<style scoped>
.group-card {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  padding: 0.8rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: var(--layout-radius-lg, 0.95rem);
  background: color-mix(in srgb, var(--layout-panel-bg) 94%, var(--layout-page-bg, transparent));
  box-shadow: var(--layout-shadow-panel);
  min-width: 0;
  max-width: 100%;
  cursor: pointer;
}

.group-card.is-selected {
  border-color: color-mix(in srgb, var(--layout-accent) 70%, var(--layout-panel-border));
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--layout-accent) 45%, transparent), var(--layout-shadow-panel);
}

.group-card__header {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem;
  min-width: 0;
}

.group-card__handle {
  width: 2.25rem;
  height: 2.25rem;
  border: 0;
  border-radius: 0.65rem;
  background: color-mix(in srgb, var(--layout-accent-soft) 70%, var(--layout-panel-bg));
  color: inherit;
  cursor: grab;
  flex-shrink: 0;
}

.group-card__handle:disabled {
  cursor: default;
  opacity: 0.4;
}

.group-card__identity {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
}

.group-card__title {
  font-weight: 700;
  white-space: nowrap;
}

.group-card__target {
  padding: 0.08rem 0.45rem;
  border-radius: 999px;
  background: var(--layout-accent-soft);
  color: var(--layout-accent-strong, var(--layout-accent));
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.group-card__moves {
  display: flex;
  align-items: center;
}

.group-card__label {
  flex: 1 1 10rem;
  min-width: 0;
  max-width: 100%;
  width: auto;
}

.group-card.is-compact .group-card__header {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  grid-template-areas:
    "handle title moves remove"
    "label label label label";
  align-items: center;
}

.group-card.is-compact .group-card__handle { grid-area: handle; }
.group-card.is-compact .group-card__identity { grid-area: title; }
.group-card.is-compact .group-card__moves { grid-area: moves; }
.group-card.is-compact .group-card__remove { grid-area: remove; }

.group-card.is-compact .group-card__label {
  grid-area: label;
  width: 100%;
}

.group-zone {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: 0.6rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: 0.8rem;
  min-width: 0;
}

.group-zone--sites {
  background: color-mix(in srgb, var(--layout-accent-soft) 48%, var(--layout-panel-bg));
}

.group-zone--people {
  background: color-mix(in srgb, var(--layout-panel-bg) 82%, var(--layout-page-bg, var(--layout-accent-soft)));
}

.group-zone.is-target {
  border-color: var(--layout-accent);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--layout-accent) 45%, transparent);
}

.group-zone.is-blocked {
  opacity: 0.48;
}

.group-zone__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.group-zone__title {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.82rem;
  font-weight: 700;
}

.group-zone__count,
.group-zone__empty,
.group-zone__hint {
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.78rem;
}

.group-zone__body {
  position: relative;
  min-height: 4.75rem;
}

.group-zone__empty,
.group-zone__hint {
  margin: 0;
}

.group-zone__empty {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  padding: 0 0.35rem;
  pointer-events: none;
}

.group-zone__list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-height: 4.75rem;
}

.site-row,
.member-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.4rem;
  border-radius: 0.7rem;
  background: var(--layout-panel-bg);
  border: 1px solid var(--layout-panel-border);
  min-height: 2.75rem;
  max-width: 100%;
  cursor: grab;
}

.site-row {
  align-items: flex-start;
  flex-wrap: wrap;
}

.site-row__head {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  flex: 0 1 auto;
  width: max-content;
  max-width: 100%;
  min-width: min(100%, max-content);
}

.site-row__title,
.member-row__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.site-row__title {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem 0.4rem;
}

.site-row__title strong,
.site-row__title span {
  white-space: normal;
  overflow-wrap: anywhere;
}

.site-row__remove {
  flex: 0 0 auto;
}

.site-row__task {
  flex: 1 1 12rem;
  width: 12rem;
  min-width: min(100%, 12rem);
  max-width: 100%;
  box-sizing: border-box;
  resize: none;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.member-row {
  align-items: center;
}

.member-row > :first-child {
  flex-shrink: 0;
}

.member-row :deep(.p-button) {
  flex: 0 0 auto;
}

.member-row.is-leader {
  border-color: color-mix(in srgb, var(--layout-accent) 55%, var(--layout-panel-border));
  background: color-mix(in srgb, var(--layout-accent-soft) 35%, var(--layout-panel-bg));
}

.member-row__text span,
.member-row__text small {
  overflow-wrap: anywhere;
  white-space: normal;
}

.member-row__text small {
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.member-row__phone {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}
</style>
