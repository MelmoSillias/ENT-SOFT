<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'

import Breadcrumb from 'primevue/breadcrumb'
import AppSidebar from '@/domains/layout/components/AppSidebar.vue'
import AppBottomNav from '@/domains/layout/components/AppBottomNav.vue'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import AppTopbar from '@/domains/layout/components/AppTopbar.vue'
import { useLayout } from '@/domains/layout/composables/useLayout'
import { useLayoutTheme } from '@/domains/layout/composables/useLayoutTheme'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useAuthStore } from '@/domains/auth/stores/auth'
import { useLayoutStore } from '@/domains/layout/stores/layout'
import { useAgencyBrandStore } from '@/domains/configuration/stores/agencyBrand'
import { appConfig } from '@/config/app'

const authStore = useAuthStore()
const layoutStore = useLayoutStore()
const agencyBrandStore = useAgencyBrandStore()
const router = useRouter()
const toast = useAppToast()

const { motionPreset, layoutStyle } = storeToRefs(layoutStore)

const {
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
  handlePrimaryNavigation,
  setMobileSidebarOpen,
  setDarkMode,
  darkMode
} = useLayout()

const { isDarkModeActive } = useLayoutTheme()
const { isAppMobile } = useAppMobileLayout()
const authEnabled = appConfig.auth.enabled

const user = computed(() => authStore.user)
const displayName = computed(() => {
  const currentUser = user.value
  if (!currentUser) {
    return brand.value.name
  }

  const fullName = [currentUser.prenom, currentUser.nom].filter(Boolean).join(' ').trim()
  return fullName || currentUser.login || currentUser.email || brand.value.name
})

const shellClasses = computed(() => ({
  'app-shell--fixed': sidebarMode.value === 'fixed',
  'app-shell--overlay': sidebarMode.value === 'overlay',
  'app-shell--collapsed': sidebarCollapsed.value,
  'app-shell--detached': layoutStyle.value === 'detached',
  'app-shell--app-mobile': isAppMobile.value
}))

const pageTransition = computed(() => {
  if (motionPreset.value === 'reduced') {
    return 'page-none'
  }
  return motionPreset.value === 'calm' ? 'page-fade' : 'page-slide'
})

const toggleDarkMode = () => {
  setDarkMode(isDarkModeActive.value ? 'light' : 'dark')
}

const logout = async () => {
  if (!authEnabled) {
    return
  }

  authStore.logout()
  toast.add({
    severity: 'info',
    summary: 'Deconnexion',
    detail: 'A bientot.',
  })
  await router.push({ name: 'login' })
}

onMounted(() => {
  if (authEnabled && authStore.isAuthenticated) {
    authStore.fetchCurrentUser().catch(() => {})
    agencyBrandStore.fetch().catch(() => {})
  }
})
</script>

<template>
  <div class="app-shell" :class="shellClasses">
    <AppSidebar
      :brand="brand"
      :menu-model="menuModel"
      :sidebar-mode="sidebarMode"
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      :user="user"
      :display-name="displayName"
      :show-profile-actions="authEnabled"
      @close-mobile-sidebar="setMobileSidebarOpen(false)"
      @toggle-sidebar="handlePrimaryNavigation"
      @logout="logout"
    />

    <div class="app-shell__main">
      <AppTopbar
        :brand="brand"
        :page-title="pageTitle"
        :page-section="pageSection"
        :user="user"
        :display-name="displayName"
        :search-placeholder="shellConfig.topbarSearchPlaceholder"
        :is-dark-mode-active="isDarkModeActive"
        :dark-mode="darkMode"
        :show-profile-actions="authEnabled"
        @toggle-navigation="handlePrimaryNavigation"
        @toggle-dark-mode="toggleDarkMode"
        @logout="logout"
      />

      <div class="app-shell__content-wrap">
        <Breadcrumb
          v-if="shellConfig.breadcrumbs && !isAppMobile"
          :home="homeBreadcrumb"
          :model="breadcrumbs"
          class="app-shell__breadcrumb"
        >
          <template #item="{ item, props }">
            <RouterLink
              v-if="item.route && !item.disabled"
              v-slot="{ href, navigate }"
              :to="item.route"
              custom
            >
              <a
                :href="href"
                :aria-label="item.ariaLabel || item.label"
                v-bind="props.action"
                @click="navigate"
              >
                <span v-if="item.icon" v-bind="props.icon" />
                <span v-if="item.label" v-bind="props.label">{{ item.label }}</span>
              </a>
            </RouterLink>
            <span
              v-else
              class="p-breadcrumb-item-link app-shell__breadcrumb-current"
              :aria-current="item.disabled ? 'page' : undefined"
              :aria-label="item.ariaLabel || item.label"
            >
              <span v-if="item.icon" v-bind="props.icon" />
              <span v-if="item.label" v-bind="props.label">{{ item.label }}</span>
            </span>
          </template>
        </Breadcrumb>

        <main class="app-shell__content">
          <RouterView v-slot="{ Component, route }">
            <Transition :name="pageTransition" mode="out-in">
              <component :is="Component" :key="route.path" />
            </Transition>
          </RouterView>
        </main>
      </div>
    </div>

    <AppBottomNav v-if="isAppMobile" />
  </div>
</template>
