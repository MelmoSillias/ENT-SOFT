<script setup>
defineProps({
  items: {
    type: Array,
    required: true
  },
  modelValue: {
    type: [String, Number],
    default: null
  },
  sticky: {
    type: Boolean,
    default: true
  },
  valueKey: {
    type: String,
    default: 'value'
  },
  labelKey: {
    type: String,
    default: 'label'
  }
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="app-mobile-segment-tabs" :class="{ 'is-sticky': sticky }" role="tablist">
    <button
      v-for="item in items"
      :key="String(item[valueKey])"
      type="button"
      role="tab"
      class="app-mobile-segment-tabs__btn"
      :class="{ 'is-active': modelValue === item[valueKey] }"
      :aria-selected="modelValue === item[valueKey]"
      @click="$emit('update:modelValue', item[valueKey])"
    >
      {{ item.shortLabel || item[labelKey] }}
    </button>
  </div>
</template>
