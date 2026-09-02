<script setup>
defineProps({
  message: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <small v-if="message" class="field-error" role="alert">{{ message }}</small>
</template>
