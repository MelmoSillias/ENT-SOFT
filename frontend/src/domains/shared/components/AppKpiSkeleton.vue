<template>
  <div class="stats-grid">
    <Card v-for="index in count" :key="index" class="stats-card stats-card--skeleton">
      <template #content>
        <div class="stats-card__content">
          <Skeleton width="3rem" height="3rem" border-radius="0.9rem" />
          <div class="stats-card__lines">
            <Skeleton width="5rem" height="0.75rem" />
            <Skeleton width="4rem" height="1.5rem" />
            <Skeleton width="7rem" height="0.75rem" />
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'

defineProps({
  count: {
    type: Number,
    default: 2,
  },
})
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
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
</style>
