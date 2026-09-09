<script setup>
import { ref } from 'vue'
import TimelineResourceCell from './TimelineResourceCell.vue'
import TimelineEventBar from './TimelineEventBar.vue'

const props = defineProps({
  row: { type: Object, required: true }, // { id, label, unassigned, events, laneCount }
  dayColumns: { type: Array, required: true },
  nowPosition: { type: Number, default: null },
  statusColors: { type: Object, default: () => ({}) },
  canCreate: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['preview-task', 'create-at'])

const trackEl = ref(null)

function onTrackClick(event) {
  if (!props.canCreate || !trackEl.value) return
  const rect = trackEl.value.getBoundingClientRect()
  const ratio = (event.clientX - rect.left) / rect.width
  emit('create-at', { resourceId: props.row.unassigned ? null : props.row.id, ratio })
}
</script>

<template>
  <div class="tl-row" role="row">
    <TimelineResourceCell
      :label="row.label"
      :photo-url="row.photoUrl"
      :unassigned="row.unassigned"
      :count="row.events.length"
      :compact="compact"
    />
    <div
      ref="trackEl"
      class="tl-row__track"
      :class="{ 'tl-row__track--creatable': canCreate }"
      :style="{ '--tl-lanes': row.laneCount }"
      @click="onTrackClick"
    >
      <div class="tl-row__grid" aria-hidden="true">
        <div v-for="day in dayColumns" :key="day.key" class="tl-row__day" :style="{ flex: day.hours.length }">
          <span v-for="h in day.hours" :key="h" class="tl-row__hour" />
        </div>
      </div>
      <span
        v-if="nowPosition !== null"
        class="tl-row__now"
        :style="{ left: `${nowPosition}%` }"
        aria-hidden="true"
      />
      <TimelineEventBar
        v-for="ev in row.events"
        :key="ev.task.id"
        :event="ev"
        :color="statusColors[ev.task.status] || '#64748b'"
        @preview="emit('preview-task', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.tl-row {
  display: flex;
  border-bottom: 1px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
  min-height: var(--tl-row-min-h);
}

.tl-row__track {
  position: relative;
  flex: 1;
  min-width: 0;
  height: calc(var(--tl-lanes) * (var(--tl-bar-h) + 0.25rem) + 0.5rem);
  min-height: var(--tl-row-min-h);
}

.tl-row__track--creatable {
  cursor: copy;
}

.tl-row__grid {
  position: absolute;
  inset: 0;
  display: flex;
  pointer-events: none;
}

.tl-row__day {
  display: flex;
  border-right: 1px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
  min-width: 0;
}

.tl-row__hour {
  flex: 1;
  min-width: var(--tl-hour-w);
  border-left: 1px dashed color-mix(in srgb, var(--layout-border, #e2e8f0) 55%, transparent);
}

.tl-row__hour:first-child {
  border-left: none;
}

.tl-row__now {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--p-red-500, #ef4444);
  z-index: 1;
  pointer-events: none;
}

.tl-row__now::before {
  content: '';
  position: absolute;
  top: -3px;
  left: -3px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--p-red-500, #ef4444);
}
</style>
