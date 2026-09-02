<template>
  <div class="stats-grid" :class="{ 'stats-grid--with-amounts': hasAmountCards }">
    <Card
      v-for="item in items"
      :key="item.label"
      class="dashboard-panel dashboard-panel--sober stats-card"
      :class="{ 'stats-card--amount': item.variant === 'amount' }"
    >
      <template #content>
        <div class="stats-card__content">
          <span class="stats-card__icon-wrap" aria-hidden="true">
            <i :class="item.icon" />
          </span>
          <div class="stats-card__body">
            <p class="stats-card__label">{{ item.label }}</p>
            <p class="stats-card__value">{{ item.value }}</p>
            <p v-if="item.hint" class="stats-card__hint">{{ item.hint }}</p>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const hasAmountCards = computed(() => props.items.some((item) => item.variant === 'amount'))
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1.15rem;
}

.stats-grid--with-amounts {
  grid-template-columns: repeat(12, minmax(0, 1fr));
}

.stats-grid--with-amounts > .stats-card {
  grid-column: span 3;
}

.stats-grid--with-amounts > .stats-card--amount {
  grid-column: span 6;
}

.stats-card.dashboard-panel:hover {
  box-shadow: none;
}

.stats-card :deep(.p-card-title) {
  display: none;
}

.stats-card :deep(.p-card-content) {
  padding: 1rem 1.1rem;
}

.stats-card--amount :deep(.p-card-content) {
  padding: 1.15rem 1.35rem;
}

.stats-card__content {
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
}

.stats-card__body {
  min-width: 0;
  flex: 1;
}

.stats-card__label,
.stats-card__value,
.stats-card__hint {
  margin: 0;
}

.stats-card__label {
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--layout-text-muted);
}

.stats-card__value {
  margin-top: 0.25rem;
  font-size: 1.55rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
  letter-spacing: -0.02em;
}

.stats-card--amount .stats-card__value {
  font-size: clamp(1.15rem, 1.8vw, 1.75rem);
  line-height: 1.25;
  overflow-wrap: anywhere;
  word-break: break-word;
  hyphens: none;
}

.stats-card__hint {
  margin-top: 0.3rem;
  font-size: 0.75rem;
  color: var(--layout-text-muted);
}

@media (max-width: 1100px) {
  .stats-grid--with-amounts > .stats-card,
  .stats-grid--with-amounts > .stats-card--amount {
    grid-column: span 6;
  }
}

@media (max-width: 640px) {
  .stats-grid--with-amounts > .stats-card,
  .stats-grid--with-amounts > .stats-card--amount {
    grid-column: span 12;
  }
}
</style>
