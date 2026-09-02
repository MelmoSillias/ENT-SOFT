<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'

import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import ConfirmPopup from 'primevue/confirmpopup'
import AppQuickNavSpeedDial from '@/domains/layout/components/AppQuickNavSpeedDial.vue'
import AppConnectivityDialog from '@/domains/shared/components/AppConnectivityDialog.vue'
import PwaUpdateBanner from '@/domains/shared/components/PwaUpdateBanner.vue'

const route = useRoute()
const showQuickNav = computed(() => route.meta.requiresAuth === true)

// Clears SpeedDial (bottom 1.25rem + 3rem button + gap). Inline style needed:
// PrimeVue sets bottom: 20px on the root, which beats class CSS without !important.
const toastPt = computed(() =>
  showQuickNav.value
    ? {
        root: {
          class: 'app-toast--above-speeddial',
          style: { bottom: '5.5rem' },
        },
      }
    : undefined,
)

// PrimeVue only pauses the dismiss timer when these handlers are provided.
function onToastMouseEnter() {}
function onToastMouseLeave() {}
</script>

<template>
  <Toast
    position="bottom-right"
    class="app-toast"
    :pt="toastPt"
    :onMouseEnter="onToastMouseEnter"
    :onMouseLeave="onToastMouseLeave"
  />
  <ConfirmDialog />
  <ConfirmPopup />
  <AppConnectivityDialog />
  <PwaUpdateBanner />
  <AppQuickNavSpeedDial v-if="showQuickNav" />
  <RouterView />
</template>
