/**
 * Evaluate route meta permission requirements against the auth store.
 * Supported meta:
 * - requiredRoles: string[]
 * - requiredModule: string (permission code prefix)
 * - requiredPermission: string | string[] (any-of when array)
 */
export function canAccessRoute(authStore, meta = {}) {
  if (meta.requiredRoles?.length && !authStore.hasRole(...meta.requiredRoles)) {
    return false
  }

  if (meta.requiredModule && !authStore.hasModuleAccess(meta.requiredModule)) {
    return false
  }

  if (!meta.requiredPermission) {
    return true
  }

  if (Array.isArray(meta.requiredPermission)) {
    return authStore.hasAnyPermission(...meta.requiredPermission)
  }

  return authStore.hasPermission(meta.requiredPermission)
}
