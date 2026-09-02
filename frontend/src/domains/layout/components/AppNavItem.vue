<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  active: {
    type: Boolean,
    default: false
  },
  collapsed: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])

const router = useRouter()

const displayLabel = computed(() => {
  if (props.collapsed && props.item.shortLabel) {
    return props.item.shortLabel
  }
  return props.item.label
})

const labelTitle = computed(() => {
  if (props.collapsed && props.item.shortLabel && props.item.shortLabel !== props.item.label) {
    return props.item.label
  }
  return undefined
})

const handleClick = () => {
  if (props.item.routeName) {
    router.push({ name: props.item.routeName })
  }
  emit('click', props.item)
}
</script>

<template>
  <button
    type="button"
    class="app-nav-item"
    :class="{
      'app-nav-item--active': active,
      'app-nav-item--collapsed': collapsed
    }"
    :title="labelTitle"
    :aria-label="item.label"
    @click="handleClick"
  >
    <i v-if="item.icon" :class="['app-nav-item__icon', item.icon]"></i>
    <span class="app-nav-item__label">{{ displayLabel }}</span>
    <span v-if="active" class="app-nav-item__active-bar"></span>
  </button>
</template>
