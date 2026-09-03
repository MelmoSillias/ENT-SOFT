import { computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'

import { appNavigation, appShellBrand, appShellDefaults } from '@/domains/layout/config/appLayout'
import { useLayoutStore } from '@/domains/layout/stores/layout'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAgencyBrandStore } from '@/domains/configuration/stores/agencyBrand'

const HOME_ROUTE_NAME = 'dashboard'

const normalizeMenuItems = (items, router, route) => {
  return items.map((item) => {
    const children = item.items ? normalizeMenuItems(item.items, router, route) : undefined
    const isActive = item.routeName
      ? item.activeRouteNames
        ? item.activeRouteNames.includes(route.name)
        : route.name === item.routeName
      : Boolean(children?.some((child) => child._active))

    return {
      ...item,
      items: children,
      class: isActive ? 'layout-menu-item-active' : '',
      command: item.routeName
        ? () => {
            router.push({ name: item.routeName })
          }
        : undefined,
      _active: isActive,
    }
  })
}

const findNavTrail = (items, routeName, trail = []) => {
  if (!routeName) {
    return null
  }

  for (const item of items) {
    const nextTrail = [...trail, item]
    const isMatch = item.routeName === routeName || item.activeRouteNames?.includes(routeName)

    if (isMatch) {
      return nextTrail
    }

    if (item.items?.length) {
      const match = findNavTrail(item.items, routeName, nextTrail)

      if (match) {
        return match
      }
    }
  }

  return null
}

const findNavTrailByLabel = (items, label, trail = []) => {
  if (!label) {
    return null
  }

  for (const item of items) {
    const nextTrail = [...trail, item]

    if (item.label === label) {
      return nextTrail
    }

    if (item.items?.length) {
      const match = findNavTrailByLabel(item.items, label, nextTrail)

      if (match) {
        return match
      }
    }
  }

  return null
}

const findAncestorKeys = (items, routeName) => {
  const trail = findNavTrail(items, routeName)

  if (!trail?.length) {
    return []
  }

  return trail.slice(0, -1).map((item) => item.key)
}

const resolveNavRoute = (item) => {
  if (item?.routeName) {
    return { name: item.routeName }
  }

  if (!item?.items?.length) {
    return undefined
  }

  for (const child of item.items) {
    const resolved = resolveNavRoute(child)

    if (resolved) {
      return resolved
    }
  }

  return undefined
}

const isHomeNavItem = (item) => item?.routeName === HOME_ROUTE_NAME || item?.key === HOME_ROUTE_NAME

const toBreadcrumbItems = (trail, route) => {
  const crumbs = []

  for (const item of trail) {
    if (isHomeNavItem(item)) {
      continue
    }

    const isExactCurrent = item.routeName === route.name
    const resolvedRoute = isExactCurrent ? undefined : resolveNavRoute(item)

    crumbs.push({
      label: item.label,
      route: resolvedRoute,
      disabled: !resolvedRoute
    })
  }

  const lastNavItem = trail[trail.length - 1]
  const currentTitle = route.meta?.title
  const alreadyHasCurrent = lastNavItem?.routeName === route.name && !isHomeNavItem(lastNavItem)

  if (currentTitle && !alreadyHasCurrent && route.name !== HOME_ROUTE_NAME) {
    crumbs.push({
      label: currentTitle,
      disabled: true
    })
  }

  return crumbs
}

const toExpandedKeyMap = (keys) => Object.fromEntries(keys.map((key) => [key, true]))

export function useLayout() {
  const route = useRoute()
  const router = useRouter()
  const layoutStore = useLayoutStore()
  const { filterNavigationItems } = usePermissions()

  const filteredNavigation = computed(() => filterNavigationItems(appNavigation))

  const {
    sidebarMode,
    sidebarCollapsed,
    mobileSidebarOpen,
    quickPanelOpen,
    preferencesOpen,
    expandedMenuKeys,
    themeName,
    accentName,
    surfaceName,
    fontName,
    density,
    radius,
    darkMode,
    motionPreset
  } = storeToRefs(layoutStore)

  const menuModel = computed(() => normalizeMenuItems(filteredNavigation.value, router, route))
  const navTrail = computed(() => {
    const byRoute = findNavTrail(filteredNavigation.value, route.name)

    if (byRoute?.length) {
      return byRoute
    }

    const section = route.meta?.section

    if (section && section !== route.meta?.title) {
      return findNavTrailByLabel(filteredNavigation.value, section) || []
    }

    return []
  })
  const pageTitle = computed(() => route.meta.title || 'Application')
  const pageSection = computed(() => {
    const trail = navTrail.value
    if (trail.length > 1) {
      return trail[0].label
    }
    return route.meta.section || appShellBrand.shortName
  })
  const homeBreadcrumb = computed(() => ({
    icon: 'pi pi-home',
    route: { name: HOME_ROUTE_NAME },
    ariaLabel: 'Tableau de bord'
  }))
  const breadcrumbs = computed(() => toBreadcrumbItems(navTrail.value, route))

  const shellConfig = computed(() => ({
    ...appShellDefaults.navigation,
    ...appShellDefaults.features
  }))

  const handlePrimaryNavigation = () => {
    if (layoutStore.isOverlayMode || window.innerWidth < 1024) {
      layoutStore.setMobileSidebarOpen(!mobileSidebarOpen.value)
      return
    }

    layoutStore.toggleSidebarCollapsed()
  }

  watch(
    () => route.name,
    (routeName) => {
      if (!routeName) {
        return
      }

      const keys = findAncestorKeys(filteredNavigation.value, routeName)

      if (keys.length) {
        layoutStore.mergeExpandedMenuKeys(toExpandedKeyMap(keys))
      }

      layoutStore.setMobileSidebarOpen(false)
    },
    { immediate: true }
  )

  const agencyBrandStore = useAgencyBrandStore()
  const brand = computed(() => ({
    ...appShellBrand,
    ...agencyBrandStore.branding,
  }))

  return {
    brand,
    shellConfig,
    menuModel,
    pageTitle,
    pageSection,
    homeBreadcrumb,
    breadcrumbs,
    sidebarMode,
    sidebarCollapsed,
    mobileSidebarOpen,
    quickPanelOpen,
    preferencesOpen,
    expandedMenuKeys,
    themeName,
    accentName,
    surfaceName,
    fontName,
    density,
    radius,
    darkMode,
    motionPreset,
    handlePrimaryNavigation,
    setMobileSidebarOpen: layoutStore.setMobileSidebarOpen,
    setQuickPanelOpen: layoutStore.setQuickPanelOpen,
    setPreferencesOpen: layoutStore.setPreferencesOpen,
    setExpandedMenuKeys: layoutStore.setExpandedMenuKeys,
    setSidebarMode: layoutStore.setSidebarMode,
    setDarkMode: layoutStore.setDarkMode
  }
}
