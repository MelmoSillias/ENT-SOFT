<script setup>
import { computed, ref } from 'vue'
import Avatar from 'primevue/avatar'
import AppPersonPreviewDialog from '@/domains/shared/components/AppPersonPreviewDialog.vue'
import {
  personDisplayName,
  personInitials,
  personPhotoUrl,
} from '@/domains/shared/utils/personDisplay'

const props = defineProps({
  name: { type: String, default: '' },
  photoUrl: { type: String, default: null },
  size: { type: String, default: 'normal' }, // large | normal | small | xlarge
  shape: { type: String, default: 'circle' },
  /** Click opens an enlarged preview with identity info */
  previewable: { type: Boolean, default: false },
  /** Person-like object (employé / prestataire / utilisateur) */
  person: { type: Object, default: null },
  /** 'employee' | 'prestataire' | 'user' | 'auto' */
  kind: { type: String, default: 'auto' },
})

const previewOpen = ref(false)

const resolvedPerson = computed(() => {
  if (props.person) return props.person
  const name = String(props.name || '').trim()
  const photo = String(props.photoUrl || '').trim()
  if (!name && !photo) return null
  return {
    name: name || undefined,
    photoUrl: photo || null,
  }
})

const displayName = computed(() => {
  if (props.name?.trim()) return props.name.trim()
  return personDisplayName(resolvedPerson.value, '')
})

const initials = computed(() => personInitials(displayName.value))

const image = computed(() => {
  const fromProp = String(props.photoUrl || '').trim()
  if (fromProp) return fromProp
  return personPhotoUrl(resolvedPerson.value) || undefined
})

function openPreview(event) {
  if (!props.previewable || !resolvedPerson.value) return
  event?.stopPropagation?.()
  event?.preventDefault?.()
  previewOpen.value = true
}
</script>

<template>
  <span
    class="app-person-avatar-root"
    :class="{ 'app-person-avatar-root--previewable': previewable }"
  >
    <span
      v-if="previewable"
      class="app-person-avatar-trigger"
      role="button"
      tabindex="0"
      :aria-label="`Agrandir la photo de ${displayName || 'cette personne'}`"
      @click="openPreview"
      @keydown.enter.prevent="openPreview"
      @keydown.space.prevent="openPreview"
    >
      <Avatar
        :image="image"
        :label="image ? undefined : initials"
        :shape="shape"
        :size="size"
        class="app-person-avatar app-person-avatar--previewable"
      />
    </span>
    <Avatar
      v-else
      :image="image"
      :label="image ? undefined : initials"
      :shape="shape"
      :size="size"
      class="app-person-avatar"
    />

    <AppPersonPreviewDialog
      v-if="previewable && previewOpen"
      v-model:visible="previewOpen"
      :person="resolvedPerson"
      :kind="kind"
    />
  </span>
</template>

<style scoped>
.app-person-avatar-root {
  display: inline-flex;
  flex-shrink: 0;
  line-height: 0;
}

.app-person-avatar-trigger {
  display: inline-flex;
  flex-shrink: 0;
  padding: 0;
  margin: 0;
  border: 0;
  background: transparent;
  cursor: zoom-in;
  border-radius: 999px;
  line-height: 0;
}

.app-person-avatar-trigger:focus-visible {
  outline: 2px solid var(--p-primary-color, #0ea5e9);
  outline-offset: 2px;
}

.app-person-avatar {
  flex-shrink: 0;
  background: color-mix(in srgb, var(--p-primary-color, #0ea5e9) 16%, transparent);
  color: var(--p-primary-color, #0284c7);
  font-weight: 700;
}

.app-person-avatar--previewable {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.app-person-avatar-trigger:hover .app-person-avatar--previewable {
  transform: scale(1.06);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--p-primary-color, #0ea5e9) 35%, transparent);
}
</style>
