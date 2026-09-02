<script setup>
import { computed, useAttrs } from 'vue'
import Select from 'primevue/select'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  options: {
    type: Array,
    default: () => [],
  },
  /** Active la barre de recherche à partir de ce nombre d'options. */
  filterThreshold: {
    type: Number,
    default: 8,
  },
  filterPlaceholder: {
    type: String,
    default: 'Rechercher…',
  },
})

const model = defineModel({ default: null })
const attrs = useAttrs()

const enableFilter = computed(() => (props.options?.length ?? 0) >= props.filterThreshold)
</script>

<template>
  <Select
    v-bind="attrs"
    v-model="model"
    :options="options"
    :filter="enableFilter"
    :filter-placeholder="enableFilter ? filterPlaceholder : undefined"
  >
    <template v-for="(_, name) in $slots" :key="name" #[name]="slotData">
      <slot :name="name" v-bind="slotData || {}" />
    </template>
  </Select>
</template>
