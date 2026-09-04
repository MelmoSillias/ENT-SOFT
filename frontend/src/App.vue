<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'

import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import ConfirmPopup from 'primevue/confirmpopup'
import AppQuickNavSpeedDial from '@/domains/layout/components/AppQuickNavSpeedDial.vue'
import AppConnectivityDialog from '@/domains/shared/components/AppConnectivityDialog.vue'
import PwaUpdateBanner from '@/domains/shared/components/PwaUpdateBanner.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

const route = useRoute()
const { isAppMobile, bottomNavOffset } = useAppMobileLayout()
const showQuickNav = computed(() => route.meta.requiresAuth === true && !isAppMobile.value)

// Clears SpeedDial / bottom nav. Inline style needed:
// PrimeVue sets bottom: 20px on the root, which beats class CSS without !important.
const toastPt = computed(() => {
  if (isAppMobile.value) {
    return {
      root: {
        class: 'app-toast--above-bottom-nav',
        style: { bottom: bottomNavOffset.value },
      },
    }
  }
  if (showQuickNav.value) {
    return {
      root: {
        class: 'app-toast--above-speeddial',
        style: { bottom: '5.5rem' },
      },
    }
  }
  return undefined
})

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
