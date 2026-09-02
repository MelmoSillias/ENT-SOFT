<script setup>
defineProps({
  code: { type: String, default: '' },
  symbole: { type: String, default: '' },
  showCode: { type: Boolean, default: true },
})
</script>

<template>
  <span class="devise-badge">
    <span v-if="symbole" class="devise-badge__symbole">{{ symbole }}</span>
    <span v-if="showCode && code" class="devise-badge__code">{{ code }}</span>
  </span>
</template>

<style scoped>
.devise-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.85rem;
}

.devise-badge__symbole {
  font-weight: 600;
}

.devise-badge__code {
  color: var(--layout-text-muted, #6c757d);
  font-size: 0.75rem;
}
</style>
