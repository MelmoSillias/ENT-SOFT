<template>
  <section class="dashboard-page parametres-page">
    <Card class="dashboard-panel">
      <template #content>
        <div class="parametres-page__intro">
          <h2>Préférences d'interface</h2>
          <p>Thème, accent, densité et disposition de la navigation.</p>
        </div>
        <AppThemeControls />
      </template>
    </Card>
  </section>
</template>

<script setup>
import Card from 'primevue/card'
import AppThemeControls from '@/domains/layout/components/AppThemeControls.vue'
</script>

<style scoped>
.parametres-page {
  min-width: 0;
}

.parametres-page__intro {
  margin-bottom: 1rem;
}

.parametres-page__intro h2 {
  margin: 0 0 0.25rem;
  font-size: 1.125rem;
}

.parametres-page__intro p {
  margin: 0;
  color: var(--text-color-secondary, #64748b);
  font-size: 0.875rem;
}
</style>
