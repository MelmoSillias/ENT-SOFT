<script setup>
import { computed, defineAsyncComponent, ref } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { useAuthStore } from '@/domains/auth/stores/auth'
import { getImportDefinition } from '@/domains/shared/utils/dataImport'

const DataImportPanel = defineAsyncComponent(() => import('@/views/configuration/DataImportPanel.vue'))

const props = defineProps({
  type: { type: String, default: '' },
  types: { type: Array, default: () => [] },
  projectId: { type: String, default: '' },
  label: { type: String, default: '' },
})
const emit = defineEmits(['imported'])


const authStore = useAuthStore()
const visible = ref(false)
const busy = ref(false)
const definition = computed(() => getImportDefinition(props.type))
const availableTypes = computed(() => (props.type ? [props.type] : props.types).filter((type) => {
  const item = getImportDefinition(type)
  return item && (!item.requiresProject || props.projectId) && authStore.hasPermission(item.permission)
}))
const canImport = computed(() => availableTypes.value.length > 0)
const title = computed(() => props.label || `Importer des ${definition.value?.label || 'données'}`)

function open() {
  if (!canImport.value) return
  busy.value = false
  visible.value = true
}

function updateVisible(value) {
  if (!value && busy.value) return
  visible.value = value
}
</script>

<template>
  <Button
    v-if="canImport"
    icon="pi pi-upload"
    text
    rounded
    size="small"
    severity="secondary"
    :aria-label="title"
    aria-haspopup="dialog"
    v-tooltip.top="title"
    @click="open"
  />
  <Dialog
    :visible="visible"
    modal
    :header="title"
    :closable="!busy"
    :close-on-escape="!busy"
    :dismissable-mask="!busy"
    :draggable="false"
    :style="{ width: 'min(1080px, 94vw)' }"
    :breakpoints="{ '640px': '94vw' }"
    class="app-data-import-dialog"
    @update:visible="updateVisible"
  >
    <div class="app-data-import-dialog__content" :aria-busy="busy">
      <DataImportPanel
        v-if="visible"
        :import-type="type"
        :allowed-types="type ? [] : availableTypes"
        :project-id="projectId"
        @busy-change="busy = $event"
        @imported="emit('imported', $event)"
      />
    </div>
    <template #footer>
      <Button label="Fermer" severity="secondary" text :disabled="busy" @click="updateVisible(false)" />
    </template>
  </Dialog>
</template>

<style scoped>
.app-data-import-dialog__content {
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  overflow-wrap: anywhere;
}
</style>
