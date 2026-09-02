import { ref } from 'vue'

/**
 * Empêche les doubles clics / soumissions concurrentes sur une action async.
 * Retourne `pending` pour lier :loading sur les boutons.
 */
export function useAsyncAction(fn) {
  const pending = ref(false)

  async function run(...args) {
    if (pending.value) {
      return
    }

    pending.value = true
    try {
      return await fn(...args)
    } finally {
      pending.value = false
    }
  }

  return { pending, run }
}
