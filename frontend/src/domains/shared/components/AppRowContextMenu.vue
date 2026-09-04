<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import ContextMenu from 'primevue/contextmenu'

const props = defineProps({
  /**
   * Builder: (row) => actions[]
   * action: { label, icon?, command?, disabled?, visible?, severity? }
   */
  actionsOf: { type: Function, required: true },
  longPressMs: { type: Number, default: 500 },
})

const menu = ref()
const menuItems = ref([])
const currentRow = ref(null)

let pressTimer = null
let pressStart = null
let longPressFired = false

const model = computed(() =>
  (menuItems.value ?? []).map((action) => ({
    label: action.label,
    icon: action.icon,
    disabled: Boolean(action.disabled || action.loading),
    class: action.severity === 'danger' ? 'app-row-context-menu__danger' : undefined,
    command: action.command,
  })),
)

function actionsFor(row) {
  return (props.actionsOf(row) ?? []).filter((a) => a.visible !== false)
}

function showFor(event, row) {
  const actions = actionsFor(row)
  if (!actions.length) return
  currentRow.value = row
  menuItems.value = actions
  menu.value?.show(event)
}

function onContextMenu(event, row) {
  event.preventDefault()
  event.stopPropagation()
  showFor(event, row)
}

function clearPress() {
  if (pressTimer) {
    clearTimeout(pressTimer)
    pressTimer = null
  }
  pressStart = null
}

function onPointerDown(event, row) {
  if (event.pointerType === 'mouse' && event.button !== 0) return
  longPressFired = false
  pressStart = { x: event.clientX, y: event.clientY }
  clearPress()
  pressTimer = setTimeout(() => {
    longPressFired = true
    const fakeEvent = {
      preventDefault() {},
      stopPropagation() {},
      clientX: pressStart?.x ?? 0,
      clientY: pressStart?.y ?? 0,
      target: event.target,
      currentTarget: event.currentTarget,
    }
    showFor(fakeEvent, row)
  }, props.longPressMs)
}

function onPointerMove(event) {
  if (!pressStart) return
  const dx = Math.abs(event.clientX - pressStart.x)
  const dy = Math.abs(event.clientY - pressStart.y)
  if (dx > 10 || dy > 10) clearPress()
}

function onPointerUp(event) {
  clearPress()
  if (longPressFired) {
    event.preventDefault()
    event.stopPropagation()
  }
}

function onPointerCancel() {
  clearPress()
}

/** Bind helpers for a row element / DataTable row */
function rowBindings(row) {
  return {
    onContextmenu: (e) => onContextMenu(e, row),
    onPointerdown: (e) => onPointerDown(e, row),
    onPointermove: onPointerMove,
    onPointerup: onPointerUp,
    onPointercancel: onPointerCancel,
  }
}

onBeforeUnmount(() => clearPress())

defineExpose({
  showFor,
  onContextMenu,
  rowBindings,
  longPressFired: () => longPressFired,
})
</script>

<template>
  <ContextMenu ref="menu" :model="model" class="app-row-context-menu" />
</template>

<style>
.app-row-context-menu__danger {
  color: var(--p-red-500, #ef4444);
}
</style>
