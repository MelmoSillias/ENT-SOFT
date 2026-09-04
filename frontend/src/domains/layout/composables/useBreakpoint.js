import { onMounted, onUnmounted, ref } from 'vue'

const queries = {
  narrow: '(max-width: 479px)',
  mobile: '(max-width: 767px)',
  tablet: '(max-width: 1023px)',
  compact: '(max-width: 1279px)',
  standalone: '(display-mode: standalone)'
}

const createMediaQueryList = (query) => {
  if (typeof window === 'undefined') {
    return null
  }

  return window.matchMedia(query)
}

const readMatches = () => {
  const narrow = createMediaQueryList(queries.narrow)
  const mobile = createMediaQueryList(queries.mobile)
  const tablet = createMediaQueryList(queries.tablet)
  const compact = createMediaQueryList(queries.compact)
  const standalone = createMediaQueryList(queries.standalone)

  const isNarrow = narrow?.matches ?? false
  const isMobile = mobile?.matches ?? false
  const isStandalone = standalone?.matches ?? false

  return {
    isNarrow,
    isMobile,
    isTablet: tablet?.matches ?? false,
    isCompact: compact?.matches ?? false,
    isStandalone,
    // App mobile: ≤479px, or installed PWA on a phone-sized viewport (≤767px)
    isAppMobile: isNarrow || (isStandalone && isMobile)
  }
}

export function useBreakpoint() {
  const isNarrow = ref(false)
  const isMobile = ref(false)
  const isTablet = ref(false)
  const isCompact = ref(false)
  const isStandalone = ref(false)
  const isAppMobile = ref(false)

  const sync = () => {
    const matches = readMatches()
    isNarrow.value = matches.isNarrow
    isMobile.value = matches.isMobile
    isTablet.value = matches.isTablet
    isCompact.value = matches.isCompact
    isStandalone.value = matches.isStandalone
    isAppMobile.value = matches.isAppMobile
  }

  const listeners = []

  onMounted(() => {
    sync()

    Object.values(queries).forEach((query) => {
      const mediaQueryList = createMediaQueryList(query)

      if (!mediaQueryList) {
        return
      }

      const handler = () => sync()
      mediaQueryList.addEventListener('change', handler)
      listeners.push({ mediaQueryList, handler })
    })
  })

  onUnmounted(() => {
    listeners.forEach(({ mediaQueryList, handler }) => {
      mediaQueryList.removeEventListener('change', handler)
    })
  })

  return {
    isNarrow,
    isMobile,
    isTablet,
    isCompact,
    isStandalone,
    isAppMobile
  }
}
