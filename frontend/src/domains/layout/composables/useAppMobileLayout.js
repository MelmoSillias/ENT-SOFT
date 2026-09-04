import { computed, watch } from 'vue'

import { useBreakpoint } from '@/domains/layout/composables/useBreakpoint'

/**
 * Single flag for native-like app mobile layout (≤479px or PWA standalone ≤767px).
 * Also toggles `data-app-mobile` on <html> for CSS that sits outside AppShell.
 */
export function useAppMobileLayout() {
  const { isAppMobile, isNarrow, isStandalone, isMobile } = useBreakpoint()

  watch(
    isAppMobile,
    (active) => {
      if (typeof document === 'undefined') {
        return
      }
      document.documentElement.toggleAttribute('data-app-mobile', Boolean(active))
    },
    { immediate: true }
  )

  const bottomNavOffset = computed(() =>
    isAppMobile.value
      ? 'calc(var(--app-bottom-nav-h) + var(--app-safe-bottom) + 0.75rem)'
      : null
  )

  return {
    isAppMobile,
    isNarrow,
    isStandalone,
    isMobile,
    bottomNavOffset
  }
}
