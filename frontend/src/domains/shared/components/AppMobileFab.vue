<script setup>
import Button from 'primevue/button'

defineProps({
  ariaLabel: {
    type: String,
    default: 'Ajouter'
  }
})

defineEmits(['click'])
</script>

<template>
  <Teleport to="body">
    <div class="app-mobile-fab">
      <Button
        icon="pi pi-plus"
        rounded
        :aria-label="ariaLabel"
        @click="$emit('click')"
      />
    </div>
  </Teleport>
</template>
