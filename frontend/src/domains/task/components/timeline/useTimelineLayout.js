import { computed, ref } from 'vue'
import { startOfDay } from '@/domains/shared/utils/dateUtils'

/**
 * Composable de layout pour la timeline ressources.
 *
 * Axe X = concaténation des fenêtres horaires visibles (startHour → endHour)
 * de chaque jour de la plage. Les positions sont exprimées en % de la largeur totale.
 *
 * @param {import('vue').Ref<Array>} tasks       tâches (avec startAt/endAt ISO ou dateDue)
 * @param {import('vue').Ref<Array>} resources   [{ id, label }] (employés)
 * @param {Object} options                       { startHour, endHour }
 */
export function useTimelineLayout(tasks, resources, options = {}) {
  const startHour = options.startHour ?? 7
  const endHour = options.endHour ?? 19
  const hoursPerDay = endHour - startHour

  const anchorDate = ref(startOfDay(new Date()))
  const scale = ref('week') // 'day' | 'week'

  const dayCount = computed(() => (scale.value === 'day' ? 1 : 7))

  const rangeStart = computed(() => {
    const d = startOfDay(anchorDate.value)
    if (scale.value === 'week') {
      // Lundi de la semaine courante
      const dow = (d.getDay() + 6) % 7
      d.setDate(d.getDate() - dow)
    }
    return d
  })

  const days = computed(() => {
    const list = []
    for (let i = 0; i < dayCount.value; i += 1) {
      const d = new Date(rangeStart.value)
      d.setDate(d.getDate() + i)
      list.push(d)
    }
    return list
  })

  const totalHours = computed(() => dayCount.value * hoursPerDay)

  /** Colonnes d'heures, groupées par jour, pour l'en-tête. */
  const dayColumns = computed(() =>
    days.value.map((day) => ({
      date: day,
      key: day.toISOString().slice(0, 10),
      hours: Array.from({ length: hoursPerDay }, (_, i) => startHour + i),
    })),
  )

  const title = computed(() => {
    const fmt = (d, opts) => d.toLocaleDateString('fr-FR', opts)
    if (scale.value === 'day') {
      return fmt(days.value[0], { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    }
    const first = days.value[0]
    const last = days.value[days.value.length - 1]
    return `${fmt(first, { day: 'numeric', month: 'short' })} – ${fmt(last, { day: 'numeric', month: 'short', year: 'numeric' })}`
  })

  /** Convertit un datetime en position (heures visibles depuis le début de plage), clampée. */
  function toVisibleHours(date) {
    const dayIdx = Math.floor((startOfDay(date) - rangeStart.value) / 86400000)
    const clampedDay = Math.min(Math.max(dayIdx, 0), dayCount.value - 1)
    const hourInDay = date.getHours() + date.getMinutes() / 60
    let clampedHour = Math.min(Math.max(hourInDay - startHour, 0), hoursPerDay)
    // Si l'événement est sur un jour hors plage, coller au bord.
    if (dayIdx < 0) clampedHour = 0
    if (dayIdx > dayCount.value - 1) clampedHour = hoursPerDay
    return clampedDay * hoursPerDay + clampedHour
  }

  /** Bornes effectives d'une tâche : startAt/endAt, sinon journée entière de dateDue. */
  function effectiveBounds(task) {
    if (task.startAt) {
      const start = new Date(task.startAt)
      const end = task.endAt ? new Date(task.endAt) : new Date(start.getTime() + 3600000)
      return { start, end, allDay: false }
    }
    if (task.dateDue) {
      const start = startOfDay(new Date(task.dateDue))
      start.setHours(startHour)
      const end = new Date(start)
      end.setHours(endHour)
      return { start, end, allDay: true }
    }
    return null
  }

  const rangeEnd = computed(() => {
    const d = new Date(days.value[days.value.length - 1])
    d.setHours(23, 59, 59, 999)
    return d
  })

  /** Position en % pour une tâche, ou null si hors plage. */
  function positionFor(task) {
    const bounds = effectiveBounds(task)
    if (!bounds) return null
    if (bounds.end < rangeStart.value || bounds.start > rangeEnd.value) return null
    const left = (toVisibleHours(bounds.start) / totalHours.value) * 100
    const right = (toVisibleHours(bounds.end) / totalHours.value) * 100
    const width = Math.max(right - left, 0.75)
    return { left, width: Math.min(width, 100 - left), allDay: bounds.allDay, bounds }
  }

  /** Empile les événements qui se chevauchent dans des « lanes » verticales. */
  function assignLanes(events) {
    const sorted = [...events].sort((a, b) => a.left - b.left || b.width - a.width)
    const laneEnds = []
    for (const ev of sorted) {
      let lane = laneEnds.findIndex((end) => ev.left >= end - 0.001)
      if (lane === -1) {
        lane = laneEnds.length
        laneEnds.push(0)
      }
      laneEnds[lane] = ev.left + ev.width
      ev.lane = lane
    }
    return { events: sorted, laneCount: Math.max(laneEnds.length, 1) }
  }

  const UNASSIGNED_ID = '__unassigned__'

  /** Lignes de la timeline : « Non assigné » en tête puis un employé par ligne. */
  const rows = computed(() => {
    const byResource = new Map()
    byResource.set(UNASSIGNED_ID, [])
    for (const r of resources.value) byResource.set(r.id, [])

    for (const task of tasks.value) {
      const pos = positionFor(task)
      if (!pos) continue
      const key = task.employeeId && byResource.has(task.employeeId) ? task.employeeId : UNASSIGNED_ID
      byResource.get(key).push({ task, ...pos })
    }

    const buildRow = (id, label, unassigned = false) => {
      const { events, laneCount } = assignLanes(byResource.get(id) ?? [])
      return { id, label, unassigned, events, laneCount }
    }

    const list = []
    const unassignedRow = buildRow(UNASSIGNED_ID, 'Non assigné', true)
    if (unassignedRow.events.length) list.push(unassignedRow)
    for (const r of resources.value) list.push(buildRow(r.id, r.label))
    return list
  })

  /** Position en % de l'indicateur « maintenant », ou null si hors plage/heures. */
  const nowPosition = computed(() => {
    const now = new Date()
    if (now < rangeStart.value || now > rangeEnd.value) return null
    const h = now.getHours() + now.getMinutes() / 60
    if (h < startHour || h > endHour) return null
    return (toVisibleHours(now) / totalHours.value) * 100
  })

  /** Convertit un ratio [0..1] de la largeur du track en datetime (arrondi à l'heure). */
  function ratioToDate(ratio) {
    const visibleHours = Math.min(Math.max(ratio, 0), 0.999) * totalHours.value
    const dayIdx = Math.floor(visibleHours / hoursPerDay)
    const hour = Math.floor(visibleHours % hoursPerDay) + startHour
    const d = new Date(days.value[dayIdx])
    d.setHours(hour, 0, 0, 0)
    return d
  }

  function goPrev() {
    const d = new Date(anchorDate.value)
    d.setDate(d.getDate() - dayCount.value)
    anchorDate.value = d
  }

  function goNext() {
    const d = new Date(anchorDate.value)
    d.setDate(d.getDate() + dayCount.value)
    anchorDate.value = d
  }

  function goToday() {
    anchorDate.value = startOfDay(new Date())
  }

  return {
    UNASSIGNED_ID,
    startHour,
    endHour,
    hoursPerDay,
    anchorDate,
    scale,
    days,
    dayColumns,
    totalHours,
    title,
    rows,
    nowPosition,
    ratioToDate,
    goPrev,
    goNext,
    goToday,
  }
}
