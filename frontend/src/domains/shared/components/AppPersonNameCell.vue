<script setup>
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'

defineProps({
  name: { type: String, required: true },
  photoUrl: { type: String, default: null },
  subtitle: { type: String, default: null },
  size: { type: String, default: 'small' },
  previewable: { type: Boolean, default: true },
  person: { type: Object, default: null },
  /** 'employee' | 'prestataire' | 'user' | 'auto' */
  kind: { type: String, default: 'auto' },
})
</script>

<template>
  <div class="app-person-name-cell">
    <AppPersonAvatar
      :name="name"
      :photo-url="photoUrl"
      :size="size"
      :previewable="previewable"
      :person="person"
      :kind="kind"
    />
    <div class="app-person-name-cell__text">
      <span class="app-person-name-cell__name">{{ name }}</span>
      <small v-if="subtitle" class="app-person-name-cell__subtitle">{{ subtitle }}</small>
    </div>
  </div>
</template>

<style scoped>
.app-person-name-cell {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 0;
}

.app-person-name-cell__text {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.app-person-name-cell__name {
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-person-name-cell__subtitle {
  color: var(--layout-text-muted, #64748b);
  font-size: 0.75rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
