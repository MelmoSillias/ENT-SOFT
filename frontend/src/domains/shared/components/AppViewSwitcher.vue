<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SelectButton from 'primevue/selectbutton'

const props = defineProps({
  options: {
    type: Array,
    required: true,
  },
})

const route = useRoute()
const router = useRouter()

const currentValue = computed(() => {
  const match = props.options.find((option) => {
    if (option.activeRouteNames?.length) {
      return option.activeRouteNames.includes(route.name)
    }
    return option.routeName === route.name
  })
  return match?.value ?? props.options[0]?.value
})

function navigate(value) {
  const option = props.options.find((item) => item.value === value)
  if (!option?.routeName || route.name === option.routeName) {
    return
  }
  router.push({ name: option.routeName })
}
</script>

<template>
  <SelectButton
    :model-value="currentValue"
    :options="options"
    option-label="label"
    option-value="value"
    aria-label="Basculer entre les vues"
    @update:model-value="navigate"
  />
</template>

<style scoped>
:deep(.p-selectbutton) {
  flex-shrink: 0;
}
</style>
