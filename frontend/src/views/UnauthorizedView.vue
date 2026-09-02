<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Card from 'primevue/card'

const route = useRoute()
const router = useRouter()

const attemptedPath = computed(() => {
  const from = route.query.from
  return typeof from === 'string' && from.length ? from : null
})

function goHome() {
  router.push({ name: 'dashboard' })
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }
  goHome()
}
</script>

<template>
  <section class="dashboard-page unauthorized-page">
    <Card class="dashboard-panel unauthorized-page__card">
      <template #content>
        <div class="unauthorized-page__icon" aria-hidden="true">
          <i class="pi pi-lock" />
        </div>
        <h1>Accès non autorisé</h1>
        <p>
          Vous n’avez pas la permission d’accéder à cette page.
          Contactez un administrateur si vous pensez qu’il s’agit d’une erreur.
        </p>
        <p v-if="attemptedPath" class="unauthorized-page__path">
          Tentative d’accès : <code>{{ attemptedPath }}</code>
        </p>
        <div class="unauthorized-page__actions">
          <Button label="Retour" icon="pi pi-arrow-left" severity="secondary" outlined @click="goBack" />
          <Button label="Tableau de bord" icon="pi pi-home" @click="goHome" />
        </div>
      </template>
    </Card>
  </section>
</template>

<style scoped>
.unauthorized-page {
  display: grid;
  place-items: center;
  min-height: min(70vh, 640px);
}

.unauthorized-page__card {
  width: min(520px, 100%);
}

.unauthorized-page__card :deep(.p-card-content) {
  display: grid;
  gap: 0.85rem;
  justify-items: center;
  text-align: center;
  padding: 1.5rem 1rem;
}

.unauthorized-page__icon {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--p-orange-500, #f59e0b) 16%, transparent);
  color: var(--p-orange-600, #d97706);
  font-size: 1.35rem;
}

.unauthorized-page h1 {
  margin: 0;
  font-size: 1.35rem;
}

.unauthorized-page p {
  margin: 0;
  color: var(--layout-text-muted, #6c757d);
  line-height: 1.5;
  max-width: 36ch;
}

.unauthorized-page__path {
  font-size: 0.85rem;
}

.unauthorized-page__path code {
  font-size: 0.8rem;
  word-break: break-all;
}

.unauthorized-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  justify-content: center;
  margin-top: 0.5rem;
}
</style>
