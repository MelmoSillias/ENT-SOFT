<template>
  <div class="stats-grid" :class="{ 'stats-grid--mobile': isAppMobile }">
    <Card v-for="index in count" :key="index" class="stats-card stats-card--skeleton">
      <template #content>
        <div class="stats-card__content">
          <Skeleton
            :width="isAppMobile ? '2.5rem' : '3rem'"
            :height="isAppMobile ? '2.5rem' : '3rem'"
            border-radius="0.9rem"
          />
          <div class="stats-card__lines">
            <Skeleton width="5rem" height="0.75rem" />
            <Skeleton width="4rem" height="1.5rem" />
            <Skeleton v-if="!isAppMobile" width="7rem" height="0.75rem" />
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

defineProps({
  count: {
    type: Number,
    default: 2,
  },
})

const { isAppMobile } = useAppMobileLayout()
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.stats-grid--mobile {
  grid-template-columns: 1fr;
  gap: var(--app-card-gap, 0.625rem);
}

.stats-card__content {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.stats-grid--mobile .stats-card__content {
  gap: 0.75rem;
  align-items: center;
}

.stats-card__lines {
  display: grid;
  gap: 0.45rem;
  flex: 1;
}

.stats-grid--mobile .stats-card__lines {
  gap: 0.35rem;
}
</style>
