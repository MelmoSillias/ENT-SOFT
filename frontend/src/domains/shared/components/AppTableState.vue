<template>
  <div class="app-table-state-root">
    <div v-if="loading" class="app-table-state-loading">
      <AppTableSkeleton
        v-if="skeleton"
        :rows="skeletonRows"
        :columns="skeletonColumns"
        :show-title="false"
        :label="loadingText"
      />
      <div v-else class="app-table-state app-table-state--loading">
        <ProgressSpinner stroke-width="4" style="width: 2.5rem; height: 2.5rem" />
        <p>{{ loadingText }}</p>
      </div>
    </div>

    <div v-else-if="error" class="app-table-state app-table-state--error">
      <i class="pi pi-exclamation-triangle" />
      <h3>{{ errorTitle }}</h3>
      <p>{{ error }}</p>
      <Button
        label="Réessayer"
        icon="pi pi-refresh"
        severity="warn"
        :loading="retrying"
        :disabled="retrying"
        @click="$emit('retry')"
      />
    </div>

    <div v-else-if="isEmpty" class="app-table-state app-table-state--empty">
      <i :class="emptyIcon" />
      <h3>{{ emptyTitle }}</h3>
      <p>{{ emptyText }}</p>
      <slot name="empty-action" />
    </div>

    <slot v-else />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import AppTableSkeleton from '@/domains/shared/components/AppTableSkeleton.vue'

defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  skeleton: {
    type: Boolean,
    default: true,
  },
  skeletonRows: {
    type: Number,
    default: 6,
  },
  skeletonColumns: {
    type: Number,
    default: 5,
  },
  isEmpty: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: null,
  },
  errorTitle: {
    type: String,
    default: 'Erreur de chargement',
  },
  retrying: {
    type: Boolean,
    default: false,
  },
  loadingText: {
    type: String,
    default: 'Chargement des données…',
  },
  emptyIcon: {
    type: String,
    default: 'pi pi-inbox',
  },
  emptyTitle: {
    type: String,
    default: 'Aucun élément',
  },
  emptyText: {
    type: String,
    default: 'Aucune donnée disponible pour le moment.',
  },
})

defineEmits(['retry'])
</script>

<style scoped>
.app-table-state-root {
  min-width: 0;
  margin-top: 1rem;
}

.app-table-state {
  display: grid;
  justify-items: center;
  gap: 1rem;
  padding: 3rem 1.5rem; 
  border: 1px dashed color-mix(in srgb, var(--pv-surface-border) 78%, transparent);
  border-radius: var(--layout-radius-lg, 0.5rem);
  background: var(--content-surface-empty, var(--content-surface-panel));
  text-align: center;
  color: var(--pv-text);
  box-shadow: var(--layout-shadow-panel);
}

.app-table-state--loading {
  min-height: 12rem;
  place-content: center;
}

.app-table-state--loading p {
  margin: 0;
  color: var(--pv-text-muted);
  font-size: 0.875rem;
}

.app-table-state--empty i {
  font-size: 2rem;
  color: var(--pv-accent);
}

.app-table-state--error {
  border-color: color-mix(in srgb, var(--p-orange-500, #f59e0b) 55%, transparent);
}

.app-table-state--error i {
  font-size: 2rem;
  color: var(--p-orange-500, #f59e0b);
}

.app-table-state h3,
.app-table-state p {
  margin: 0;
}

.app-table-state p {
  color: var(--pv-text-muted);
}

.app-table-state--empty h3 {
  color: var(--pv-text);
}
</style>
