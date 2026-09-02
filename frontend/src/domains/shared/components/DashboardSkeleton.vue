<template>
  <div class="dashboard-skeleton">
    <div class="dashboard-skeleton__overview">
      <div class="stats-grid stats-grid--with-amounts">
        <Card
          v-for="index in 6"
          :key="`stat-${index}`"
          class="stats-card stats-card--skeleton"
          :class="{ 'stats-card--amount': index <= 2 }"
        >
          <template #content>
            <div class="stats-card__content">
              <Skeleton width="3rem" height="3rem" border-radius="0.9rem" />
              <div class="stats-card__lines">
                <Skeleton width="5rem" height="0.75rem" />
                <Skeleton :width="index <= 2 ? '10rem' : '4rem'" height="1.5rem" />
                <Skeleton width="7rem" height="0.75rem" />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <Card class="dashboard-panel dashboard-panel--compact dashboard-panel--sober">
        <template #title>
          <Skeleton width="10rem" height="1rem" />
        </template>
        <template #content>
          <div class="dashboard-skeleton__etats">
            <Skeleton v-for="index in 5" :key="`etat-${index}`" height="3.5rem" border-radius="0.75rem" />
          </div>
        </template>
      </Card>
    </div>

    <Card v-if="showExchangeRates" class="dashboard-panel dashboard-panel--sober exchange-rates-panel">
      <template #title>
        <div class="dashboard-skeleton__section-header">
          <Skeleton width="2rem" height="2rem" border-radius="0.5rem" />
          <div class="dashboard-skeleton__section-lines">
            <Skeleton width="8rem" height="0.875rem" />
            <Skeleton width="12rem" height="0.75rem" />
          </div>
        </div>
      </template>
      <template #content>
        <div class="dashboard-skeleton__tabs">
          <Skeleton v-for="index in 3" :key="`tab-${index}`" width="5.5rem" height="2rem" border-radius="0.375rem" />
        </div>
        <div class="dashboard-skeleton__rates">
          <Skeleton v-for="index in 6" :key="`rate-${index}`" height="5rem" border-radius="0.75rem" />
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'

defineProps({
  showExchangeRates: {
    type: Boolean,
    default: true,
  },
})
</script>

<style scoped>
.dashboard-skeleton__overview {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
  align-items: stretch;
  margin-bottom: 1.25rem;
}

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

.stats-card__content {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.stats-card__lines {
  display: grid;
  gap: 0.45rem;
  flex: 1;
}

.dashboard-skeleton__etats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 0.5rem;
}

.dashboard-skeleton__section-header {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.dashboard-skeleton__section-lines {
  display: grid;
  gap: 0.35rem;
}

.dashboard-skeleton__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 1rem;
}

.dashboard-skeleton__rates {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 0.65rem;
}

</style>
