import { computed, ref } from 'vue'
import { hasFormErrors } from '@/domains/shared/utils/formValidation'

/**
 * Affiche les erreurs de champs après une tentative de soumission.
 * @param {() => Record<string, unknown>} getErrors
 */
export function useFormFieldErrors(getErrors) {
  const showErrors = ref(false)

  const errors = computed(() => {
    if (!showErrors.value) {
      return {}
    }
    return getErrors() ?? {}
  })

  const isValid = computed(() => !hasFormErrors(getErrors() ?? {}))

  function validate() {
    showErrors.value = true
    return !hasFormErrors(getErrors() ?? {})
  }

  function resetErrors() {
    showErrors.value = false
  }

  function fieldError(field) {
    const value = errors.value?.[field]
    if (Array.isArray(value)) {
      return value.filter(Boolean).join(' ')
    }
    return typeof value === 'string' ? value : ''
  }

  function hasError(field) {
    return Boolean(fieldError(field))
  }

  return {
    showErrors,
    errors,
    isValid,
    validate,
    resetErrors,
    fieldError,
    hasError,
  }
}
