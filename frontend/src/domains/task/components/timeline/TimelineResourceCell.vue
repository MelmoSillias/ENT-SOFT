<script setup>
import { computed } from 'vue'
import Avatar from 'primevue/avatar'

const props = defineProps({
  label: { type: String, required: true },
  unassigned: { type: Boolean, default: false },
  count: { type: Number, default: 0 },
  compact: { type: Boolean, default: false },
})

const initials = computed(() =>
  props.label
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join(''),
)
</script>

<template>
  <div class="tl-resource tl-sticky-left" :class="{ 'tl-resource--unassigned': unassigned }" :title="label">
    <Avatar
      :label="unassigned ? '?' : initials"
      shape="circle"
      size="small"
      class="tl-resource__avatar"
    />
    <span v-if="!compact" class="tl-resource__label">{{ label }}</span>
    <span v-if="!compact && count" class="tl-resource__count">{{ count }}</span>
  </div>
</template>

<style scoped>
.tl-resource {
  width: var(--tl-res-w);
  min-width: var(--tl-res-w);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-right: 1px solid var(--layout-border, var(--p-content-border-color, #e2e8f0));
  font-size: 0.8125rem;
  overflow: hidden;
}

.tl-resource--unassigned {
  font-style: italic;
  color: var(--p-text-muted-color, #64748b);
}

.tl-resource__avatar {
  flex-shrink: 0;
  font-size: 0.6875rem;
}

.tl-resource__label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.tl-resource__count {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #64748b);
  background: color-mix(in srgb, var(--p-primary-color, #3b82f6) 12%, transparent);
  border-radius: 999px;
  padding: 0.05rem 0.45rem;
}
</style>
