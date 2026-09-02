<script setup>
import { ref } from 'vue'
import { registerSW } from 'virtual:pwa-register'

const needRefresh = ref(false)

const updateSW = registerSW({
  immediate: true,
  onNeedRefresh() {
    needRefresh.value = true
  },
})

function applyUpdate() {
  updateSW(true)
}

function dismiss() {
  needRefresh.value = false
}
</script>

<template>
  <div v-if="needRefresh" class="pwa-update-banner" role="status">
    <i class="pi pi-refresh" aria-hidden="true" />
    <span>Une nouvelle version est disponible.</span>
    <button type="button" class="pwa-update-banner__action" @click="applyUpdate">
      Actualiser
    </button>
    <button type="button" class="pwa-update-banner__dismiss" aria-label="Ignorer" @click="dismiss">
      <i class="pi pi-times" aria-hidden="true" />
    </button>
  </div>
</template>

<style scoped>
.pwa-update-banner {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--content-space-sm, 0.5rem);
  padding: var(--content-space-xs, 0.5rem) var(--content-space-md, 1rem);
  padding-bottom: calc(var(--content-space-xs, 0.5rem) + env(safe-area-inset-bottom, 0px));
  border-top: 1px solid color-mix(in srgb, var(--layout-accent, #1a3066) 35%, transparent);
  background: var(--layout-panel-bg, #ffffff);
  color: var(--layout-text-color, #1a2744);
  font-size: var(--content-font-meta, 0.875rem);
}

.pwa-update-banner__action {
  margin-left: var(--content-space-2xs, 0.25rem);
  border: 0;
  background: transparent;
  color: var(--layout-accent, #1a3066);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 0.15em;
}

.pwa-update-banner__dismiss {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  margin-left: var(--content-space-2xs, 0.25rem);
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.pwa-update-banner__dismiss:hover,
.pwa-update-banner__action:hover {
  filter: brightness(0.92);
}
</style>
