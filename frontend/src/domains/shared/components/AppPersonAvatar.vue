<script setup>
import { computed } from 'vue'
import Avatar from 'primevue/avatar'
import { personInitials } from '@/domains/shared/utils/personDisplay'

const props = defineProps({
  name: { type: String, default: '' },
  photoUrl: { type: String, default: null },
  size: { type: String, default: 'normal' }, // large | normal | small | xlarge
  shape: { type: String, default: 'circle' },
})

const initials = computed(() => personInitials(props.name))
const image = computed(() => {
  const url = String(props.photoUrl || '').trim()
  return url || undefined
})
</script>

<template>
  <Avatar
    :image="image"
    :label="image ? undefined : initials"
    :shape="shape"
    :size="size"
    class="app-person-avatar"
  />
</template>

<style scoped>
.app-person-avatar {
  flex-shrink: 0;
  background: color-mix(in srgb, var(--p-primary-color, #0ea5e9) 16%, transparent);
  color: var(--p-primary-color, #0284c7);
  font-weight: 700;
}
</style>
