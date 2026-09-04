<template>
  <div class="dashboard-skeleton" :class="{ 'dashboard-skeleton--mobile': isAppMobile }">
    <div class="dashboard-skeleton__overview">
      <div class="stats-grid stats-grid--with-amounts">
        <Card
          v-for="index in kpiCount"
          :key="`stat-${index}`"
          class="stats-card stats-card--skeleton"
          :class="{ 'stats-card--amount': index <= 2 }"
        >
          <template #content>
            <div class="stats-card__content">
              <Skeleton
                :width="isAppMobile ? '2.5rem' : '3rem'"
                :height="isAppMobile ? '2.5rem' : '3rem'"
                border-radius="0.9rem"
              />
              <div class="stats-card__lines">
                <Skeleton width="5rem" height="0.75rem" />
                <Skeleton :width="index <= 2 ? (isAppMobile ? '8rem' : '10rem') : '4rem'" height="1.5rem" />
                <Skeleton v-if="!isAppMobile || index <= 2" width="7rem" height="0.75rem" />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <Card class="dashboard-panel dashboard-panel--compact dashboard-panel--sober">
        <template #title>
          <Skeleton :width="isAppMobile ? '7rem' : '10rem'" height="1rem" />
        </template>
        <template #content>
          <div class="dashboard-skeleton__etats">
            <Skeleton
              v-for="index in etatCount"
              :key="`etat-${index}`"
              :height="isAppMobile ? '2.75rem' : '3.5rem'"
              border-radius="0.75rem"
            />
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
            <Skeleton v-if="!isAppMobile" width="12rem" height="0.75rem" />
          </div>
        </div>
      </template>
      <template #content>
        <div class="dashboard-skeleton__tabs">
          <Skeleton v-for="index in 3" :key="`tab-${index}`" width="5.5rem" height="2rem" border-radius="0.375rem" />
        </div>
        <div class="dashboard-skeleton__rates">
          <Skeleton
            v-for="index in rateCount"
            :key="`rate-${index}`"
            :height="isAppMobile ? '4.25rem' : '5rem'"
            border-radius="0.75rem"
          />
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

defineProps({
  showExchangeRates: {
    type: Boolean,
    default: true,
  },
})

const { isAppMobile } = useAppMobileLayout()

const kpiCount = computed(() => (isAppMobile.value ? 4 : 6))
const etatCount = computed(() => (isAppMobile.value ? 4 : 5))
const rateCount = computed(() => (isAppMobile.value ? 4 : 6))
</script>

<style scoped>
.dashboard-skeleton__overview {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
  align-items: stretch;
  margin-bottom: 1.25rem;
}

.dashboard-skeleton--mobile .dashboard-skeleton__overview {
  gap: var(--app-card-gap, 0.625rem);
  margin-bottom: var(--app-card-gap, 0.625rem);
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

.dashboard-skeleton--mobile .stats-grid {
  gap: var(--app-card-gap, 0.625rem);
}

.dashboard-skeleton--mobile .stats-grid--with-amounts > .stats-card,
.dashboard-skeleton--mobile .stats-grid--with-amounts > .stats-card--amount {
  grid-column: span 12;
}

.stats-card__content {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.dashboard-skeleton--mobile .stats-card__content {
  gap: 0.75rem;
  align-items: center;
}

.stats-card__lines {
  display: grid;
  gap: 0.45rem;
  flex: 1;
}

.dashboard-skeleton--mobile .stats-card__lines {
  gap: 0.35rem;
}

.dashboard-skeleton__etats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 0.5rem;
}

.dashboard-skeleton--mobile .dashboard-skeleton__etats {
  grid-template-columns: 1fr 1fr;
  gap: 0.4rem;
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

.dashboard-skeleton--mobile .dashboard-skeleton__tabs {
  flex-wrap: nowrap;
  overflow: hidden;
  margin-bottom: 0.75rem;
}

.dashboard-skeleton__rates {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 0.65rem;
}

.dashboard-skeleton--mobile .dashboard-skeleton__rates {
  grid-template-columns: 1fr 1fr;
  gap: var(--app-card-gap, 0.625rem);
}
</style>
