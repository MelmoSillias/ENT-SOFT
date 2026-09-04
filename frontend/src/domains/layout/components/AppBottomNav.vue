<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useLayout } from '@/domains/layout/composables/useLayout'

const emit = defineEmits(['open-more'])

const route = useRoute()
const router = useRouter()
const { menuModel, setMobileSidebarOpen } = useLayout()

const PRIMARY_KEYS = ['dashboard', 'projects', 'clients', 'finance']

const primaryItems = computed(() => {
  const flat = flattenMenu(menuModel.value || [])
  return PRIMARY_KEYS.map((key) => flat.find((item) => item.key === key)).filter(Boolean)
})

function flattenMenu(items) {
  const result = []
  for (const item of items) {
    result.push(item)
    if (item.items?.length) {
      result.push(...flattenMenu(item.items))
    }
  }
  return result
}

function isItemActive(item) {
  if (!item) return false
  if (item.activeRouteNames?.length) {
    return item.activeRouteNames.includes(route.name)
  }
  return route.name === item.routeName
}

function navigateTo(item) {
  if (!item?.routeName) return
  router.push({ name: item.routeName })
}

function openMore() {
  setMobileSidebarOpen(true)
  emit('open-more')
}

const labelFor = (item) => item.shortLabel || item.label
</script>

<template>
  <nav class="app-bottom-nav" aria-label="Navigation principale">
    <button
      v-for="item in primaryItems"
      :key="item.key"
      type="button"
      class="app-bottom-nav__item"
      :class="{ 'is-active': isItemActive(item) }"
      :aria-current="isItemActive(item) ? 'page' : undefined"
      @click="navigateTo(item)"
    >
      <i :class="item.icon" aria-hidden="true" />
      <span>{{ labelFor(item) }}</span>
    </button>

    <button type="button" class="app-bottom-nav__item" aria-label="Plus de modules" @click="openMore">
      <i class="pi pi-bars" aria-hidden="true" />
      <span>Plus</span>
    </button>
  </nav>
</template>
