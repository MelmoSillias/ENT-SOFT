<script setup>
defineProps({
  dayColumns: { type: Array, required: true },
})

function dayLabel(date) {
  return date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' })
}

function isToday(date) {
  const now = new Date()
  return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth() && date.getDate() === now.getDate()
}
</script>

<template>
  <div class="tl-header" role="row">
    <div class="tl-header__resource tl-sticky-left" role="columnheader">Ressources</div>
    <div class="tl-header__track">
      <div
        v-for="day in dayColumns"
        :key="day.key"
        class="tl-header__day"
        :class="{ 'tl-header__day--today': isToday(day.date) }"
        :style="{ flex: day.hours.length }"
      >
        <div class="tl-header__day-label">{{ dayLabel(day.date) }}</div>
        <div class="tl-header__hours">
          <span v-for="h in day.hours" :key="h" class="tl-header__hour">{{ String(h).padStart(2, '0') }}h</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tl-header {
  display: flex;
  position: sticky;
  top: 0;
  z-index: 3;
  background: var(--p-content-background, #fff);
  border-bottom: 2px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
}

.tl-header__resource {
  width: var(--tl-res-w);
  min-width: var(--tl-res-w);
  display: flex;
  align-items: center;
  font-weight: 600;
  font-size: 0.8125rem;
  padding: 0.5rem 0.75rem;
  border-right: 1px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
}

.tl-header__track {
  display: flex;
  flex: 1;
  min-width: 0;
}

.tl-header__day {
  border-right: 1px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
  min-width: 0;
}

.tl-header__day--today .tl-header__day-label {
  color: var(--p-primary-color, #3b82f6);
}

.tl-header__day-label {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.35rem 0.5rem 0.15rem;
  text-transform: capitalize;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tl-header__hours {
  display: flex;
}

.tl-header__hour {
  flex: 1;
  min-width: var(--tl-hour-w);
  font-size: 0.6875rem;
  color: var(--p-text-muted-color, #64748b);
  padding: 0.1rem 0 0.35rem 0.25rem;
  border-left: 1px dashed color-mix(in srgb, var(--layout-border, #e2e8f0) 70%, transparent);
  white-space: nowrap;
}

.tl-header__hour:first-child {
  border-left: none;
}
</style>
