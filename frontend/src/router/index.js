import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/domains/auth/stores/auth'
import { canAccessRoute } from '@/domains/auth/utils/canAccessRoute'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true, forceLight: true, title: 'Connexion' },
  },
  {
    path: '/',
    component: () => import('@/domains/layout/components/AppShell.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/views/dashboard/DashboardView.vue'),
        meta: {
          title: 'Tableau de bord',
          section: 'ENT',
          requiredPermission: 'dashboard.view',
        },
      },
      {
        path: 'projects',
        name: 'projects',
        component: () => import('@/views/project/ProjectListView.vue'),
        meta: {
          title: 'Projets',
          section: 'Projets',
          requiredModule: 'project',
        },
      },
      {
        path: 'projects/:id',
        name: 'project-detail',
        component: () => import('@/views/project/ProjectDetailView.vue'),
        meta: {
          title: 'Détail projet',
          section: 'Projets',
          hiddenFromMenu: true,
          requiredModule: 'project',
        },
      },
      {
        path: 'sites',
        name: 'sites',
        component: () => import('@/views/site/SiteListView.vue'),
        meta: {
          title: 'Sites',
          section: 'Sites',
          requiredModule: 'site',
        },
      },
      {
        path: 'tasks',
        name: 'tasks',
        component: () => import('@/views/task/TaskListView.vue'),
        meta: {
          title: 'Planning & Tâches',
          section: 'Planning',
          requiredModule: 'task',
        },
      },
      {
        path: 'clients',
        name: 'clients',
        component: () => import('@/views/client/ClientListView.vue'),
        meta: {
          title: 'Clients',
          section: 'Clients',
          requiredModule: 'client',
        },
      },
      {
        path: 'clients/:id',
        name: 'client-detail',
        component: () => import('@/views/client/ClientDetailView.vue'),
        meta: {
          title: 'Détail client',
          section: 'Clients',
          hiddenFromMenu: true,
          requiredModule: 'client',
        },
      },
      {
        path: 'stock/equipments',
        name: 'equipments',
        component: () => import('@/views/stock/EquipmentListView.vue'),
        meta: {
          title: 'Matériels et équipements',
          section: 'Matériels',
          requiredModule: 'stock',
        },
      },
      {
        path: 'stock/equipments/:id',
        name: 'equipment-detail',
        component: () => import('@/views/stock/EquipmentDetailView.vue'),
        meta: {
          title: 'Détail équipement',
          section: 'Matériels',
          hiddenFromMenu: true,
          requiredModule: 'stock',
        },
      },
      {
        path: 'employees',
        name: 'employees',
        component: () => import('@/views/employee/RhView.vue'),
        meta: {
          title: 'RH',
          section: 'RH',
          requiredModule: 'employee',
        },
      },
      {
        path: 'employees/:id',
        name: 'employee-detail',
        component: () => import('@/views/employee/EmployeeDetailView.vue'),
        meta: {
          title: 'Détail employé',
          section: 'RH',
          hiddenFromMenu: true,
          requiredModule: 'employee',
        },
      },
      {
        path: 'prestataires/:id',
        name: 'prestataire-detail',
        component: () => import('@/views/employee/PrestataireDetailView.vue'),
        meta: {
          title: 'Détail prestataire',
          section: 'RH',
          hiddenFromMenu: true,
          requiredPermission: 'employee.prestataires.view',
        },
      },
      {
        path: 'finances',
        name: 'finances',
        component: () => import('@/views/finance/FinanceView.vue'),
        meta: {
          title: 'Finances',
          section: 'Finances',
          requiredModule: 'finance',
        },
      },
      {
        path: 'invoices',
        redirect: { name: 'finances', query: { tab: 'invoices' } },
      },
      {
        path: 'users',
        name: 'users',
        component: () => import('@/views/access/UserListView.vue'),
        meta: {
          title: 'Utilisateurs',
          section: 'Administration',
          requiredPermission: 'access.users.manage',
        },
      },
      {
        path: 'audit',
        name: 'audit',
        component: () => import('@/views/access/AuditLogView.vue'),
        meta: {
          title: 'Journal d\'audit',
          section: 'Administration',
          requiredPermission: 'access.audit.view',
        },
      },
      {
        path: 'configurations',
        name: 'configurations',
        component: () => import('@/views/configuration/ConfigurationView.vue'),
        meta: { title: 'Paramètres', section: 'Paramètres' },
      },
      {
        path: 'parametres',
        redirect: { name: 'configurations', query: { tab: 'appearance' } },
      },
      {
        path: 'settings',
        redirect: { name: 'configurations', query: { tab: 'settings' } },
      },
      {
        path: 'profil',
        name: 'profile',
        component: () => import('@/views/profile/ProfileView.vue'),
        meta: {
          title: 'Profil',
          section: 'Compte',
          hiddenFromMenu: true,
        },
      },
      {
        path: 'unauthorized',
        name: 'unauthorized',
        component: () => import('@/views/UnauthorizedView.vue'),
        meta: {
          title: 'Accès non autorisé',
          section: 'Sécurité',
          hiddenFromMenu: true,
        },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.name === 'login' && auth.isAuthenticated) {
    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        auth.logout()
        return true
      }
    }
    return { name: 'dashboard' }
  }

  if (to.meta.public) return true
  if (!auth.isAuthenticated) return { name: 'login' }
  if (!auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.logout()
      return { name: 'login' }
    }
  }

  if (to.name === 'unauthorized') {
    return true
  }

  const matchedWithAccess = to.matched.filter((record) =>
    record.meta?.requiredPermission
    || record.meta?.requiredModule
    || record.meta?.requiredRoles?.length,
  )

  for (const record of matchedWithAccess) {
    if (!canAccessRoute(auth, record.meta)) {
      return {
        name: 'unauthorized',
        query: { from: to.fullPath },
      }
    }
  }

  return true
})

export default router
