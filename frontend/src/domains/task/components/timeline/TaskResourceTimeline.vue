<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import TimelineToolbar from './TimelineToolbar.vue'
import TimelineHeader from './TimelineHeader.vue'
import TimelineRow from './TimelineRow.vue'
import { useTimelineLayout } from './useTimelineLayout'
import { taskStatusLabel } from '@/domains/shared/utils/entLabels'

const props = defineProps({
  tasks: { type: Array, required: true },
  resources: { type: Array, required: true }, // [{ id, label }]
  statusColors: { type: Object, default: () => ({}) },
  canCreate: { type: Boolean, default: false },
  mobile: { type: Boolean, default: false },
})

const emit = defineEmits(['preview-task', 'create-at'])

const {
  scale,
  dayColumns,
  totalHours,
  title,
  rows,
  nowPosition,
  ratioToDate,
  goPrev,
  goNext,
  goToday,
} = useTimelineLayout(toRef(props, 'tasks'), toRef(props, 'resources'))

const scrollEl = ref(null)

const legend = computed(() =>
  Object.entries(props.statusColors).map(([status, color]) => ({ status, label: taskStatusLabel(status), color })),
)

function scrollToNow({ smooth = false } = {}) {
  const container = scrollEl.value
  if (!container || nowPosition.value == null) return

  const canvas = container.querySelector('.tl-canvas')
  if (!canvas) return

  const sticky = canvas.querySelector('.tl-sticky-left')
  const resW = sticky?.offsetWidth ?? 0
  const trackW = Math.max(canvas.scrollWidth - resW, 0)
  const nowX = resW + (nowPosition.value / 100) * trackW
  const maxScroll = Math.max(canvas.scrollWidth - container.clientWidth, 0)
  const target = Math.min(Math.max(nowX - container.clientWidth / 2, 0), maxScroll)

  container.scrollTo({ left: target, behavior: smooth ? 'smooth' : 'auto' })
}

function scheduleScrollToNow(options) {
  nextTick(() => {
    requestAnimationFrame(() => scrollToNow(options))
  })
}

onMounted(() => scheduleScrollToNow())
watch([nowPosition, scale, dayColumns], () => scheduleScrollToNow())

function onCreateAt({ resourceId, ratio }) {
  emit('create-at', { resourceId, start: ratioToDate(ratio) })
}
</script>

<template>
  <div class="tl" :class="{ 'tl--mobile': mobile }">
    <TimelineToolbar
      v-model:scale="scale"
      :title="title"
      :compact="mobile"
      @prev="goPrev"
      @next="goNext"
      @today="goToday"
    />

    <div ref="scrollEl" class="tl-scroll" role="table" aria-label="Timeline des tâches par employé">
      <div class="tl-canvas" :style="{ '--tl-hours': totalHours }">
        <TimelineHeader :day-columns="dayColumns" />
        <TimelineRow
          v-for="row in rows"
          :key="row.id"
          :row="row"
          :day-columns="dayColumns"
          :now-position="nowPosition"
          :status-colors="statusColors"
          :can-create="canCreate"
          :compact="mobile"
          @preview-task="emit('preview-task', $event)"
          @create-at="onCreateAt"
        />
        <p v-if="!rows.length" class="tl-empty">Aucun employé à afficher.</p>
      </div>
    </div>

    <div class="tl-legend">
      <slot name="legend-label"><span class="tl-legend__label">Statuts :</span></slot>
      <span v-for="item in legend" :key="item.status" class="tl-legend__item">
        <span class="tl-legend__dot" :style="{ background: item.color }" />
        {{ item.label }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.tl {
  --tl-res-w: 12rem;
  --tl-hour-w: 3.5rem;
  --tl-bar-h: 2.25rem;
  --tl-row-min-h: 3rem;
}

.tl--mobile {
  --tl-res-w: 3.25rem;
  --tl-hour-w: 2.75rem;
  --tl-bar-h: 2.5rem;
  --tl-row-min-h: 3.25rem;
}

.tl-scroll {
  overflow-x: auto;
  border: 1px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
  border-radius: 0.5rem;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-x: contain;
}

.tl-canvas {
  min-width: calc(var(--tl-res-w) + var(--tl-hours) * var(--tl-hour-w));
}

:deep(.tl-sticky-left) {
  position: sticky;
  left: 0;
  z-index: 2;
  background: var(--p-content-background, #fff);
}

.tl-empty {
  padding: 1rem;
  color: var(--p-text-muted-color, #64748b);
  font-size: 0.875rem;
}

.tl-legend {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding-top: 0.65rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #64748b);
}

.tl-legend__label {
  font-weight: 600;
}

.tl-legend__item {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.tl-legend__dot {
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 50%;
  display: inline-block;
}
</style>
