<script setup>
import { computed } from 'vue'

const props = defineProps({
  event: { type: Object, required: true }, // { task, left, width, lane, allDay, bounds }
  color: { type: String, default: '#64748b' },
  interactive: { type: Boolean, default: true },
})

const emit = defineEmits(['preview'])

const timeLabel = computed(() => {
  const { bounds, allDay } = props.event
  if (allDay) return 'Journée'
  const fmt = (d) => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${fmt(bounds.start)} – ${fmt(bounds.end)}`
})

function onPreview(domEvent) {
  if (!props.interactive) return
  emit('preview', { event: domEvent, task: props.event.task })
}
</script>

<template>
  <button
    type="button"
    class="tl-event"
    :class="{ 'tl-event--allday': event.allDay, 'tl-event--static': !interactive }"
    :style="{
      left: `${event.left}%`,
      width: `${event.width}%`,
      top: `calc(${event.lane} * (var(--tl-bar-h) + 0.25rem) + 0.375rem)`,
      '--tl-event-color': color,
    }"
    :tabindex="interactive ? 0 : -1"
    :aria-label="event.task.title"
    @click.stop="onPreview"
  >
    <span class="tl-event__title">{{ event.task.title }}</span>
    <span class="tl-event__time">{{ timeLabel }}</span>
  </button>
</template>

<style scoped>
.tl-event {
  position: absolute;
  height: var(--tl-bar-h);
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.05rem;
  padding: 0.15rem 0.5rem;
  border: none;
  border-left: 3px solid var(--tl-event-color);
  border-radius: 0.375rem;
  background: color-mix(in srgb, var(--tl-event-color) 18%, var(--p-content-background, #fff));
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  overflow: hidden;
  min-width: 1.25rem;
  transition: filter 0.15s ease, box-shadow 0.15s ease;
}

.tl-event--static {
  cursor: default;
}

.tl-event--allday {
  border-left-style: dashed;
  background: repeating-linear-gradient(
    -45deg,
    color-mix(in srgb, var(--tl-event-color) 14%, var(--p-content-background, #fff)),
    color-mix(in srgb, var(--tl-event-color) 14%, var(--p-content-background, #fff)) 8px,
    color-mix(in srgb, var(--tl-event-color) 7%, var(--p-content-background, #fff)) 8px,
    color-mix(in srgb, var(--tl-event-color) 7%, var(--p-content-background, #fff)) 16px
  );
}

.tl-event:hover,
.tl-event:focus-visible {
  filter: brightness(1.04);
  box-shadow: 0 1px 4px rgb(0 0 0 / 0.18);
  z-index: 2;
  outline: none;
}

.tl-event__title {
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}

.tl-event__time {
  font-size: 0.65rem;
  color: var(--p-text-muted-color, #64748b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.1;
}
</style>
