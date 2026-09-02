<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Drawer from 'primevue/drawer'
import Button from 'primevue/button'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

const router = useRouter()
const { canAccessMenuItem } = usePermissions()

const visible = defineModel({
  type: Boolean,
  required: true,
})

const allActions = [
  {
    label: 'Nouveau projet',
    icon: 'pi pi-briefcase',
    routeName: 'projects',
    requiredModule: 'project',
  },
  {
    label: 'Planning',
    icon: 'pi pi-calendar',
    routeName: 'tasks',
    requiredModule: 'task',
  },
  {
    label: 'Clients',
    icon: 'pi pi-users',
    routeName: 'clients',
    requiredModule: 'client',
  },
  {
    label: 'Tableau de bord',
    icon: 'pi pi-home',
    routeName: 'dashboard',
    requiredPermission: 'dashboard.view',
  },
]

const quickActions = computed(() => allActions.filter((action) => canAccessMenuItem(action)))

function navigate(routeName) {
  visible.value = false
  router.push({ name: routeName })
}
</script>

<template>
  <Drawer
    :visible="visible"
    position="right"
    class="app-topbar-quick-panel"
    @update:visible="visible = $event"
  >
    <template #header>
      <div class="app-topbar-quick-panel__header">
        <p class="app-topbar-quick-panel__eyebrow">Accès rapide</p>
        <h2 class="app-topbar-quick-panel__title">Actions métier</h2>
      </div>
    </template>

    <div class="app-topbar-quick-panel__content">
      <Button
        v-for="action in quickActions"
        :key="action.routeName"
        :label="action.label"
        :icon="action.icon"
        severity="secondary"
        fluid
        class="app-topbar-quick-panel__action"
        @click="navigate(action.routeName)"
      />
      <p v-if="!quickActions.length" class="app-topbar-quick-panel__empty">
        Aucune action disponible avec vos permissions.
      </p>
    </div>
  </Drawer>
</template>

<style scoped>
.app-topbar-quick-panel__content {
  display: grid;
  gap: 0.75rem;
}

.app-topbar-quick-panel__empty {
  margin: 0;
  color: var(--layout-text-muted, #6c757d);
  font-size: 0.9rem;
}
</style>
