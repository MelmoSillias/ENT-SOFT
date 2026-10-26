<script setup>
import { computed } from 'vue'
import draggable from 'vuedraggable'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'

const props = defineProps({
  group: { type: Object, required: true },
  index: { type: Number, required: true },
  canEdit: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
  showPlace: { type: Boolean, default: false },
  canMoveUp: { type: Boolean, default: false },
  canMoveDown: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'remove', 'place', 'return-member', 'site-added', 'move'])

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

function isLeader(member) {
  return props.group.leaderEmployeeId === member.id
}

function toggleLeader(member) {
  props.group.leaderEmployeeId = isLeader(member) ? null : member.id
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
  if (!added) return
  emit('site-added', { siteId: added.siteId, index: event.newIndex })
}
</script>

<template>
  <article class="group-card" :class="{ 'is-selected': selected }">
    <header class="group-card__header">
      <button type="button" class="group-card__handle" aria-label="Déplacer le groupe" :disabled="!canEdit">
        <i class="pi pi-bars" />
      </button>
      <button type="button" class="group-card__title no-drag" @click="emit('select')">
        Groupe {{ index + 1 }}
      </button>
      <Button
        class="no-drag"
        icon="pi pi-chevron-up"
        text
        rounded
        severity="secondary"
        aria-label="Monter le groupe"
        :disabled="!canEdit || !canMoveUp"
        @click="emit('move', -1)"
      />
      <Button
        class="no-drag"
        icon="pi pi-chevron-down"
        text
        rounded
        severity="secondary"
        aria-label="Descendre le groupe"
        :disabled="!canEdit || !canMoveDown"
        @click="emit('move', 1)"
      />
      <InputText
        v-model="group.label"
        class="group-card__label no-drag"
        placeholder="Libellé, ex. installation AAU"
        :disabled="!canEdit"
      />
      <Button
        class="no-drag"
        icon="pi pi-trash"
        text
        rounded
        severity="danger"
        aria-label="Supprimer le groupe"
        :disabled="!canEdit"
        @click="emit('remove')"
      />
    </header>

    <Button
      v-if="showPlace"
      class="no-drag group-card__place"
      label="Placer ici"
      icon="pi pi-arrow-down"
      size="small"
      @click="emit('place')"
    />

    <draggable
      v-model="group.sites"
      item-key="siteId"
      class="group-card__sites"
      :group="{ name: 'planning-sites', pull: true, put: true }"
      v-bind="dragOptions"
      @add="onSiteAdd"
    >
      <template #item="{ element, index: siteIndex }">
        <div class="site-row">
          <div class="site-row__title">
            <strong>{{ element.code }}</strong>
            <span>{{ element.title }}</span>
          </div>
          <InputText
            v-model="element.description"
            class="site-row__task no-drag"
            placeholder="Tâche sur ce site"
            :disabled="!canEdit"
          />
          <Button
            class="no-drag"
            icon="pi pi-times"
            text
            rounded
            severity="secondary"
            aria-label="Retirer le site"
            :disabled="!canEdit"
            @click="removeSite(siteIndex)"
          />
        </div>
      </template>
    </draggable>
    <p v-if="!group.sites.length" class="group-card__hint">Ajoutez un ou plusieurs sites.</p>

    <draggable
      v-model="group.members"
      item-key="id"
      class="group-card__members"
      :group="{ name: 'planning-people', pull: true, put: true }"
      v-bind="dragOptions"
    >
      <template #item="{ element }">
        <div class="member-row" :class="{ 'is-leader': isLeader(element) }">
          <AppPersonAvatar
            class="no-drag"
            :name="element.name"
            :photo-url="element.photoUrl"
            size="small"
            :previewable="false"
          />
          <div class="member-row__text">
            <span>{{ element.name }}</span>
            <small v-if="element.mention">{{ element.mention }}</small>
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
            @click="toggleLeader(element)"
          />
          <Button
            class="no-drag"
            icon="pi pi-times"
            text
            rounded
            severity="secondary"
            aria-label="Retirer du groupe"
            :disabled="!canEdit"
            @click="removeMember(element)"
          />
        </div>
      </template>
    </draggable>
    <p v-if="!group.members.length" class="group-card__hint">Glissez des personnes ici, ou appuyez puis placez.</p>
    <p v-else-if="!group.leaderEmployeeId" class="group-card__hint">Désignez le chef avec l’étoile.</p>
  </article>
</template>

<style scoped>
.group-card {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding: 0.75rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: 0.9rem;
  background: color-mix(in srgb, var(--layout-panel-bg) 88%, var(--p-content-hover-background, transparent));
  min-width: 0;
}

.group-card.is-selected {
  border-color: var(--p-primary-color);
}

.group-card__header {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem;
  min-width: 0;
}

.group-card__handle,
.group-card__title {
  border: 0;
  background: transparent;
  color: inherit;
}

.group-card__handle {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.6rem;
  cursor: grab;
  flex-shrink: 0;
}

.group-card__handle:disabled {
  cursor: default;
  opacity: 0.4;
}

.group-card__title {
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  padding: 0.35rem 0.15rem;
}

.group-card__label {
  flex: 1 1 10rem;
  min-width: 0;
  width: auto;
}

.group-card__place {
  align-self: stretch;
  min-height: 2.75rem;
}

.group-card__sites,
.group-card__members {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-height: 2.75rem;
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
  cursor: grab;
}

.site-row {
  align-items: stretch;
  flex-wrap: wrap;
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
  align-items: baseline;
  gap: 0.4rem;
  flex: 1 1 8rem;
}

.site-row__task {
  flex: 2 1 12rem;
  min-width: 8rem;
  width: auto;
}

.member-row.is-leader {
  border-color: color-mix(in srgb, var(--p-primary-color) 55%, var(--layout-panel-border));
}

.member-row__text span,
.site-row__title span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-row__text small {
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.group-card__hint {
  margin: 0;
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-size: 0.8rem;
}
</style>
