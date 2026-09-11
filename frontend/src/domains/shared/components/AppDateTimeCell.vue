<script setup>
import { computed } from 'vue'
import { formatDateFr, formatTimeFr, hasTimeComponent } from '@/domains/shared/utils/entLabels'

const props = defineProps({
  value: {
    type: [String, Date, Number],
    default: null,
  },
  /** Optional datetime used for the time line when `value` has no time (e.g. createdAt). */
  timeFrom: {
    type: [String, Date, Number],
    default: null,
  },
  /** Force showing / hiding the time line */
  showTime: {
    type: Boolean,
    default: null,
  },
})

const dateLabel = computed(() => formatDateFr(props.value))

const timeLabel = computed(() => {
  if (props.showTime === false) return ''
  if (hasTimeComponent(props.value)) return formatTimeFr(props.value)
  if (hasTimeComponent(props.timeFrom)) return formatTimeFr(props.timeFrom)
  if (props.showTime === true && props.value) return formatTimeFr(props.value)
  return ''
})
</script>

<template>
  <div class="app-date-time-cell">
    <span class="app-date-time-cell__date">{{ dateLabel }}</span>
    <small v-if="timeLabel" class="app-date-time-cell__time">{{ timeLabel }}</small>
  </div>
</template>

<style scoped>
.app-date-time-cell {
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
  line-height: 1.25;
  min-width: 0;
}

.app-date-time-cell__time {
  color: var(--p-text-muted-color, #64748b);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
}
</style>
