<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import SpeedDial from 'primevue/speeddial'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

const router = useRouter()
const { canAccessMenuItem } = usePermissions()

const dialStyle = {
  position: 'fixed',
  right: '1.25rem',
  bottom: '1.25rem',
  left: 'auto',
  top: 'auto',
  zIndex: 1100,
}

const allItems = [
  {
    label: 'Projets',
    icon: 'pi pi-briefcase',
    requiredModule: 'project',
    command: () => router.push({ name: 'projects' }),
  },
  {
    label: 'Planning',
    icon: 'pi pi-calendar',
    requiredModule: 'task',
    command: () => router.push({ name: 'tasks' }),
  },
  {
    label: 'Clients',
    icon: 'pi pi-users',
    requiredModule: 'client',
    command: () => router.push({ name: 'clients' }),
  },
  {
    label: 'Factures',
    icon: 'pi pi-wallet',
    requiredModule: 'finance',
    command: () => router.push({ name: 'invoices' }),
  },
  {
    label: 'Tableau de bord',
    icon: 'pi pi-home',
    requiredPermission: 'dashboard.view',
    command: () => router.push({ name: 'dashboard' }),
  },
  {
    label: 'Paramètres',
    icon: 'pi pi-cog',
    command: () => router.push({ name: 'configurations' }),
  },
]

const items = computed(() => allItems.filter((item) => canAccessMenuItem(item)))
</script>

<template>
  <Teleport v-if="items.length" to="body">
    <SpeedDial
      :model="items"
      direction="up"
      type="linear"
      :transition-delay="40"
      :style="dialStyle"
      :button-props="{ rounded: true, severity: 'primary', 'aria-label': 'Navigation rapide' }"
      class="app-quick-nav-speeddial"
      show-icon="pi pi-bolt"
      hide-icon="pi pi-times"
    />
  </Teleport>
</template>

<style>
/* Hors flux document : la liste ne doit jamais agrandir la page. */
.app-quick-nav-speeddial.p-speeddial {
  position: fixed !important;
  right: 1.25rem !important;
  bottom: 1.25rem !important;
  left: auto !important;
  top: auto !important;
  z-index: 1100;
  width: auto !important;
  height: auto !important;
  margin: 0;
  padding: 0;
  align-items: flex-end !important;
}

.app-quick-nav-speeddial .p-speeddial-list {
  position: absolute !important;
  right: 0;
  bottom: calc(100% + 0.5rem);
  left: auto !important;
  top: auto !important;
  width: max-content;
  margin: 0;
  padding: 0;
  gap: 0.5rem;
}

.app-quick-nav-speeddial .p-speeddial-button.p-button {
  width: 3rem !important;
  height: 3rem !important;
  min-width: 3rem !important;
  padding: 0 !important;
  aspect-ratio: 1;
  border-radius: 50% !important;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.28) !important;
}

@media (max-width: 768px) {
  .app-quick-nav-speeddial.p-speeddial {
    right: 1rem !important;
    bottom: 1rem !important;
  }
}
</style>
