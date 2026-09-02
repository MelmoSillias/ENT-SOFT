<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

import { useConnectivityStore } from '@/domains/shared/stores/connectivity'

const connectivity = useConnectivityStore()
const { status, dialogVisible, checking, showBanner, isDown } = storeToRefs(connectivity)

onMounted(() => {
  connectivity.startMonitoring()
})

onUnmounted(() => {
  connectivity.stopMonitoring()
})

const title = computed(() =>
  status.value === 'browserOffline'
    ? 'Connexion Internet interrompue'
    : 'Serveur inaccessible',
)

const description = computed(() => {
  if (status.value === 'browserOffline') {
    return 'Votre appareil semble hors ligne. Vérifiez votre connexion Internet, puis réessayez.'
  }
  return "Impossible de joindre le serveur de l'application. Vérifiez le réseau ou réessayez dans un instant."
})

const bannerLabel = computed(() =>
  status.value === 'browserOffline'
    ? 'Hors ligne — connexion Internet perdue'
    : 'Serveur inaccessible — connexion interrompue',
)

function onDialogVisibleUpdate(visible) {
  connectivity.setDialogVisible(visible)
}

async function retry() {
  await connectivity.checkNow()
}
</script>

<template>
  <Dialog
    :visible="dialogVisible"
    modal
    closable
    dismissable-mask
    :close-on-escape="true"
    :header="title"
    class="app-connectivity-dialog"
    style="width: min(420px, 95vw)"
    @update:visible="onDialogVisibleUpdate"
  >
    <div class="app-connectivity-dialog__body">
      <div class="app-connectivity-dialog__icon" aria-hidden="true">
        <i :class="status === 'browserOffline' ? 'pi pi-globe' : 'pi pi-server'" />
      </div>
      <p class="app-connectivity-dialog__text">{{ description }}</p>
    </div>

    <template #footer>
      <Button
        label="Réessayer"
        icon="pi pi-refresh"
        :loading="checking"
        :disabled="checking"
        @click="retry"
      />
    </template>
  </Dialog>

  <button
    v-if="showBanner && isDown"
    type="button"
    class="app-connectivity-banner"
    @click="connectivity.openDialog()"
  >
    <i class="pi pi-exclamation-triangle" aria-hidden="true" />
    <span>{{ bannerLabel }}</span>
    <span class="app-connectivity-banner__action">Détails</span>
  </button>
</template>

<style scoped>
.app-connectivity-dialog__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--content-space-md);
  text-align: center;
  padding-block: var(--content-space-xs);
}

.app-connectivity-dialog__icon {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--content-radius-md);
  background: var(--content-tone-warn-soft);
  color: var(--content-tone-warn-strong);
  font-size: 1.25rem;
}

.app-connectivity-dialog__text {
  margin: 0;
  color: var(--content-text-muted);
  font-size: var(--content-font-body);
  line-height: 1.5;
}

.app-connectivity-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--content-space-sm);
  padding: var(--content-space-xs) var(--content-space-md);
  border: 0;
  border-bottom: 1px solid color-mix(in srgb, var(--content-tone-warn) 35%, transparent);
  background: var(--content-tone-warn-soft);
  color: var(--content-tone-warn-strong);
  font: inherit;
  font-size: var(--content-font-meta);
  cursor: pointer;
  text-align: center;
}

.app-connectivity-banner:hover {
  filter: brightness(0.98);
}

.app-connectivity-banner__action {
  margin-left: var(--content-space-2xs);
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 0.15em;
}
</style>
