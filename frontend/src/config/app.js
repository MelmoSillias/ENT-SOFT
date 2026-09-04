const deploymentModeRaw = String(import.meta.env.VITE_DEPLOYMENT_MODE || 'online').toLowerCase()
const deploymentMode = deploymentModeRaw === 'local' ? 'local' : 'online'

const pollMsRaw = Number(import.meta.env.VITE_CONNECTIVITY_POLL_MS)
const connectivityPollMs = Number.isFinite(pollMsRaw) && pollMsRaw >= 3000 ? pollMsRaw : 15000

export const appConfig = {
  app: {
    id: 'ent-soft',
    name: 'ENT-SOFT',
    title: 'ENT-SOFT',
    subtitle: 'Entreprise Network Technologies',
    description: 'Application métier pour Entreprise Network Technologies — télécoms, réseaux, solaires et solutions.',
  },
  branding: {
    name: 'ENT',
    shortName: 'ENT',
    tagline: 'CONNECTER • ALIMENTER • ÉQUIPER • INNOVER',
    logoUrl: '/branding/logo.png',
    supportEmail: 'support@ent-soft.local',
  },
  connectivity: {
    /** @type {'online' | 'local'} */
    deploymentMode,
    pollMs: connectivityPollMs,
    healthTimeoutMs: 5000,
    failureThreshold: 2,
  },
  navigation: {
    variant: 'sidebar-topbar',
    sidebarMode: 'fixed',
    sidebarCollapsed: false,
    topbarSearchPlaceholder: 'Rechercher une vue ou un module',
    items: [
      {
        key: 'dashboard',
        label: 'Tableau de bord',
        shortLabel: 'Accueil',
        icon: 'pi pi-home',
        routeName: 'dashboard',
        requiredPermission: 'dashboard.view',
      },
      {
        key: 'projects',
        label: 'Projets',
        icon: 'pi pi-briefcase',
        routeName: 'projects',
        activeRouteNames: ['projects', 'project-detail'],
        requiredModule: 'project',
      },
      {
        key: 'sites',
        label: 'Sites',
        icon: 'pi pi-map-marker',
        routeName: 'sites',
        requiredModule: 'site',
      },
      {
        key: 'tasks',
        label: 'Planning & Tâches',
        shortLabel: 'Planning',
        icon: 'pi pi-calendar',
        routeName: 'tasks',
        requiredModule: 'task',
      },
      {
        key: 'clients',
        label: 'Clients',
        icon: 'pi pi-users',
        routeName: 'clients',
        activeRouteNames: ['clients', 'client-detail'],
        requiredModule: 'client',
      },
      {
        key: 'stock',
        label: 'Matériels et équipements',
        shortLabel: 'Matériels',
        icon: 'pi pi-box',
        routeName: 'equipments',
        activeRouteNames: ['equipments', 'equipment-detail'],
        requiredModule: 'stock',
      },
      {
        key: 'employees',
        label: 'RH',
        shortLabel: 'RH',
        icon: 'pi pi-id-card',
        routeName: 'employees',
        activeRouteNames: ['employees', 'employee-detail', 'prestataire-detail'],
        requiredModule: 'employee',
      },
      {
        key: 'finance',
        label: 'Finances',
        icon: 'pi pi-wallet',
        routeName: 'finances',
        activeRouteNames: ['finances', 'invoices'],
        requiredModule: 'finance',
      },
      {
        key: 'administration',
        label: 'Utilisateurs',
        shortLabel: 'Utilis.',
        icon: 'pi pi-shield',
        routeName: 'users',
        requiredPermission: 'access.users.manage',
      },
      {
        key: 'configurations',
        label: 'Paramètres',
        shortLabel: 'Config',
        icon: 'pi pi-cog',
        routeName: 'configurations',
      },
    ],
  },
  auth: {
    enabled: true,
  },
  storage: {
    layoutPreferencesKey: 'ent-soft-layout',
  },
  routes: {
    homeRouteName: 'dashboard',
    loginRouteName: 'login',
  },
}
