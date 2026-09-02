<template>
  <div class="app-table-skeleton" role="status" aria-live="polite" aria-busy="true">
    <div v-if="showTitle" class="app-table-skeleton__title">
      <Skeleton width="12rem" height="1.25rem" />
    </div>

    <div class="app-table-skeleton__table">
      <div class="app-table-skeleton__head">
        <Skeleton
          v-for="index in columns"
          :key="`h-${index}`"
          :width="columnWidth(index)"
          height="0.7rem"
          border-radius="0.35rem"
        />
      </div>

      <div v-for="row in rows" :key="`r-${row}`" class="app-table-skeleton__row">
        <Skeleton
          v-for="index in columns"
          :key="`c-${row}-${index}`"
          :width="columnWidth(index, row)"
          height="0.85rem"
          border-radius="0.35rem"
        />
      </div>
    </div>

    <p class="app-table-skeleton__label">{{ label }}</p>
  </div>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'

defineProps({
  rows: {
    type: Number,
    default: 6,
  },
  columns: {
    type: Number,
    default: 5,
  },
  showTitle: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: 'Chargement des données…',
  },
})

function columnWidth(index, row = 0) {
  const base = [18, 22, 14, 20, 16, 24]
  const width = base[(index + row) % base.length]
  return `${width + ((row * 3 + index) % 8)}%`
}
</script>

<style scoped>
.app-table-skeleton {
  display: grid;
  gap: 0.85rem;
  padding: 0.25rem 0 0.5rem;
}

.app-table-skeleton__title {
  margin-bottom: 0.25rem;
}

.app-table-skeleton__table {
  display: grid;
  gap: 0;
  border: 1px solid var(--layout-panel-border, var(--pv-surface-border));
  border-radius: var(--layout-radius-md, 0.75rem);
  overflow: hidden;
  background: color-mix(in srgb, var(--layout-panel-bg, var(--pv-surface-bg)) 92%, transparent);
}

.app-table-skeleton__head,
.app-table-skeleton__row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(4.5rem, 1fr));
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
}

.app-table-skeleton__head {
  background: color-mix(in srgb, var(--layout-accent-soft, rgba(26, 48, 102, 0.08)) 70%, transparent);
  border-bottom: 1px solid var(--layout-panel-border, var(--pv-surface-border));
}

.app-table-skeleton__row + .app-table-skeleton__row {
  border-top: 1px solid color-mix(in srgb, var(--layout-panel-border, var(--pv-surface-border)) 70%, transparent);
}

.app-table-skeleton__row:nth-child(even) {
  background: color-mix(in srgb, var(--layout-page-bg, transparent) 55%, transparent);
}

.app-table-skeleton__label {
  margin: 0;
  font-size: 0.78rem;
  color: var(--layout-text-muted, var(--pv-text-muted));
  text-align: center;
}
</style>
