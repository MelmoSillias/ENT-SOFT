<script setup>
import { computed, ref } from 'vue'
import Popover from 'primevue/popover'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import {
  taskStatusLabel,
  taskStatusSeverity,
  formatDateFr,
  formatDateTimeFr,
  TASK_STATUS_OPTIONS,
} from '@/domains/shared/utils/entLabels'

const props = defineProps({
  siteMap: { type: Object, default: () => ({}) },
  employeeMap: { type: Object, default: () => ({}) },
  statusColors: { type: Object, default: () => ({}) },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  statusUpdating: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'delete', 'status-change'])

const popover = ref()
const task = ref(null)
const lastAnchor = ref(null)

const accent = computed(() => props.statusColors[task.value?.status] || 'var(--layout-accent, #10b981)')
const siteLabel = computed(() => (task.value?.siteId && props.siteMap[task.value.siteId]) || '—')
const employeeLabel = computed(() =>
  task.value?.employeeId ? props.employeeMap[task.value.employeeId] || '—' : 'Non assigné',
)
const hasSchedule = computed(() => Boolean(task.value?.startAt || task.value?.endAt))
const hasActions = computed(() => props.canEdit || props.canDelete)

function show(event, nextTask, target) {
  const anchor = target ?? event?.currentTarget
  if (task.value?.id === nextTask?.id && lastAnchor.value === anchor) {
    popover.value?.toggle(event, anchor)
    return
  }
  task.value = nextTask
  lastAnchor.value = anchor
  popover.value?.show(event, anchor)
}

function hide() {
  popover.value?.hide()
}

function patchTask(partial) {
  if (!task.value) return
  task.value = { ...task.value, ...partial }
}

function onEdit() {
  const current = task.value
  hide()
  if (current) emit('edit', current)
}

function onDelete() {
  const current = task.value
  hide()
  if (current) emit('delete', current)
}

function onStatus(status) {
  if (!props.canEdit || !task.value || task.value.status === status || props.statusUpdating) return
  emit('status-change', { task: task.value, status })
}

defineExpose({ show, hide, patchTask })
</script>

<template>
  <Popover ref="popover" class="task-preview-popover" :pt="{ content: { class: 'task-preview-popover__content' } }">
    <div v-if="task" class="task-preview" :style="{ '--task-preview-accent': accent }">
      <header class="task-preview__header">
        <div class="task-preview__heading">
          <h3 class="task-preview__title">{{ task.title }}</h3>
          <Tag :value="taskStatusLabel(task.status)" :severity="taskStatusSeverity(task.status)" />
        </div>
        <p v-if="task.description" class="task-preview__description">{{ task.description }}</p>
        <p v-else class="task-preview__description task-preview__description--empty">Aucune description</p>
      </header>

      <dl class="task-preview__meta">
        <div class="task-preview__row">
          <dt><i class="pi pi-map-marker" aria-hidden="true" /> Site</dt>
          <dd>{{ siteLabel }}</dd>
        </div>
        <div class="task-preview__row">
          <dt><i class="pi pi-user" aria-hidden="true" /> Employé</dt>
          <dd>{{ employeeLabel }}</dd>
        </div>
        <div class="task-preview__row">
          <dt><i class="pi pi-calendar" aria-hidden="true" /> Échéance</dt>
          <dd>{{ formatDateFr(task.dateDue) }}</dd>
        </div>
        <div v-if="hasSchedule" class="task-preview__row">
          <dt><i class="pi pi-clock" aria-hidden="true" /> Planning</dt>
          <dd>
            <template v-if="task.startAt && task.endAt">
              {{ formatDateTimeFr(task.startAt) }} → {{ formatDateTimeFr(task.endAt) }}
            </template>
            <template v-else-if="task.startAt">Début {{ formatDateTimeFr(task.startAt) }}</template>
            <template v-else>Fin {{ formatDateTimeFr(task.endAt) }}</template>
          </dd>
        </div>
      </dl>

      <div v-if="canEdit" class="task-preview__statuses" role="group" aria-label="Changer le statut">
        <button
          v-for="opt in TASK_STATUS_OPTIONS"
          :key="opt.value"
          type="button"
          class="task-preview__status"
          :class="{ 'task-preview__status--active': task.status === opt.value }"
          :style="{ '--status-color': statusColors[opt.value] || accent }"
          :disabled="statusUpdating"
          :aria-pressed="task.status === opt.value"
          @click="onStatus(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>

      <footer v-if="hasActions" class="task-preview__actions">
        <Button
          v-if="canEdit"
          label="Modifier"
          icon="pi pi-pencil"
          size="small"
          fluid
          @click="onEdit"
        />
        <Button
          v-if="canDelete"
          label="Supprimer"
          icon="pi pi-trash"
          size="small"
          severity="danger"
          outlined
          fluid
          @click="onDelete"
        />
      </footer>
    </div>
  </Popover>
</template>

<style>
.task-preview-popover.p-popover {
  max-width: min(22rem, calc(100vw - 1.5rem));
}

.task-preview-popover .p-popover-content,
.task-preview-popover__content {
  padding: 0;
}
</style>

<style scoped>
.task-preview {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 0.9rem 1rem 1rem;
  border-left: 3px solid var(--task-preview-accent);
}

.task-preview__heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.65rem;
}

.task-preview__title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--layout-text-color, var(--p-text-color));
}

.task-preview__description {
  margin: 0.45rem 0 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--layout-text-muted, var(--p-text-muted-color));
  white-space: pre-wrap;
  display: -webkit-box;
  line-clamp: 4;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.task-preview__description--empty {
  font-style: italic;
  opacity: 0.75;
}

.task-preview__meta {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: 0.7rem 0.75rem;
  border-radius: var(--layout-radius-sm, 0.5rem);
  background: color-mix(in srgb, var(--layout-panel-bg, #fff) 82%, var(--layout-accent-soft, transparent));
  border: 1px solid var(--layout-panel-border, var(--p-content-border-color, #e2e8f0));
}

.task-preview__row {
  display: grid;
  grid-template-columns: 7.25rem 1fr;
  gap: 0.5rem;
  align-items: start;
  font-size: 0.78rem;
}

.task-preview__row dt {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  color: var(--layout-text-muted, var(--p-text-muted-color));
  font-weight: 500;
}

.task-preview__row dt .pi {
  font-size: 0.7rem;
  opacity: 0.85;
}

.task-preview__row dd {
  margin: 0;
  color: var(--layout-text-color, var(--p-text-color));
  font-weight: 600;
  line-height: 1.35;
  word-break: break-word;
}

.task-preview__statuses {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.task-preview__status {
  border: 1px solid color-mix(in srgb, var(--status-color) 35%, var(--layout-panel-border, #e2e8f0));
  background: color-mix(in srgb, var(--status-color) 10%, transparent);
  color: var(--layout-text-color, var(--p-text-color));
  border-radius: 999px;
  padding: 0.22rem 0.65rem;
  font: inherit;
  font-size: 0.7rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 150ms ease,
    border-color 150ms ease,
    color 150ms ease;
}

.task-preview__status:hover:not(:disabled) {
  background: color-mix(in srgb, var(--status-color) 18%, transparent);
}

.task-preview__status--active {
  background: color-mix(in srgb, var(--status-color) 88%, black 4%);
  border-color: transparent;
  color: #fff;
}

.task-preview__status:disabled {
  opacity: 0.55;
  cursor: wait;
}

.task-preview__actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr));
  gap: 0.45rem;
}
</style>
