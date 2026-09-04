<script setup>
defineProps({
  flush: {
    type: Boolean,
    default: false
  }
})
</script>

<template>
  <section class="app-mobile-page dashboard-page" :class="{ 'app-mobile-page--flush': flush }">
    <header v-if="$slots.header" class="app-mobile-page__header">
      <slot name="header" />
    </header>
    <div class="app-mobile-page__body">
      <slot />
    </div>
    <slot name="fab" />
  </section>
</template>

<style scoped>
.app-mobile-page__header {
  margin-bottom: 0.35rem;
}

.app-mobile-page--flush .app-mobile-page__body :deep(.dashboard-panel) {
  margin-inline: 0;
  border-radius: 0;
  border-left: 0;
  border-right: 0;
}
</style>
