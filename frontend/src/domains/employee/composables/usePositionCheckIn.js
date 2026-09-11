import { computed, ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useGeolocation } from '@/domains/shared/maps/composables/useGeolocation'
import { checkInPosition } from '@/domains/employee/services/employeePositionService'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

/**
 * Confirm + GPS one-shot + API check-in for technicians.
 */
export function usePositionCheckIn() {
  const confirm = useConfirm()
  const toast = useAppToast()
  const { hasPermission } = usePermissions()
  const { locating, locate } = useGeolocation()
  const saving = ref(false)

  const canCheckIn = computed(() => hasPermission('employee.positions.checkin'))
  const isBusy = computed(() => locating.value || saving.value)

  async function runCheckIn() {
    if (isBusy.value) return

    const coords = await locate({ maximumAge: 0 })
    if (!coords) {
      toast.add({
        severity: 'warn',
        summary: 'Position',
        detail: 'Impossible d’obtenir votre position GPS.',
      })
      return
    }

    saving.value = true
    try {
      await checkInPosition({
        latitude: coords.lat,
        longitude: coords.lng,
        accuracy: coords.accuracy ?? null,
      })
      toast.add({
        severity: 'success',
        summary: 'Position',
        detail: 'Votre position a été enregistrée.',
      })
    } catch (e) {
      toast.add({
        severity: 'error',
        summary: 'Position',
        detail: e.response?.data?.error || 'Échec de l’enregistrement.',
      })
    } finally {
      saving.value = false
    }
  }

  function askCheckIn() {
    if (!canCheckIn.value) return
    confirm.require({
      header: 'Enregistrer ma position',
      message: 'Enregistrer votre position GPS actuelle ? Les 10 dernières positions sont conservées.',
      icon: 'pi pi-map-marker',
      rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
      acceptProps: { label: 'Enregistrer', severity: 'primary' },
      accept: () => {
        runCheckIn()
      },
    })
  }

  return {
    canCheckIn,
    locating,
    saving,
    isBusy,
    askCheckIn,
    runCheckIn,
  }
}
