<script setup>
import { computed, ref, watch } from 'vue'
import Button from 'primevue/button'
import Message from 'primevue/message'
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'

const props = defineProps({
  modelValue: { type: String, default: null },
  personName: { type: String, default: '' },
  label: { type: String, default: 'Photo' },
  disabled: { type: Boolean, default: false },
  uploading: { type: Boolean, default: false },
  removing: { type: Boolean, default: false },
  /** When true, file select emits upload immediately; otherwise emit pending-file */
  immediate: { type: Boolean, default: true },
  error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'upload', 'remove', 'pending-file'])

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
const ACCEPTED_IMAGE_ACCEPT = ACCEPTED_IMAGE_TYPES.join(',')
const MAX_BYTES = 2 * 1024 * 1024

const inputRef = ref(null)
const localError = ref(null)
const pendingPreview = ref(null)

const previewUrl = computed(() => pendingPreview.value || (String(props.modelValue || '').trim() || null))
const displayError = computed(() => props.error || localError.value)

watch(
  () => props.modelValue,
  () => {
    if (pendingPreview.value) {
      URL.revokeObjectURL(pendingPreview.value)
      pendingPreview.value = null
    }
  },
)

function validateFile(file) {
  if (!file) {
    return 'Aucun fichier sélectionné.'
  }
  if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
    return 'Seules les images JPEG, PNG, WebP et GIF sont acceptées.'
  }
  if (file.size > MAX_BYTES) {
    return 'La photo ne doit pas dépasser 2 Mo.'
  }
  return null
}

function onSelected(event) {
  const file = event.target.files?.[0]
  if (inputRef.value) {
    inputRef.value.value = ''
  }
  localError.value = null
  if (!file) {
    return
  }
  const validationError = validateFile(file)
  if (validationError) {
    localError.value = validationError
    return
  }
  if (pendingPreview.value) {
    URL.revokeObjectURL(pendingPreview.value)
  }
  pendingPreview.value = URL.createObjectURL(file)
  if (props.immediate) {
    emit('upload', file)
  } else {
    emit('pending-file', file)
  }
}

function onRemove() {
  localError.value = null
  if (pendingPreview.value) {
    URL.revokeObjectURL(pendingPreview.value)
    pendingPreview.value = null
  }
  emit('pending-file', null)
  emit('remove')
}
</script>

<template>
  <div class="field app-photo-upload">
    <label v-if="label">{{ label }}</label>
    <div class="app-photo-upload__row">
      <div class="app-photo-upload__preview" aria-live="polite">
        <img
          v-if="previewUrl"
          :src="previewUrl"
          :alt="personName || 'Photo'"
          class="app-photo-upload__image"
        >
        <AppPersonAvatar
          v-else
          :name="personName"
          size="large"
        />
      </div>
      <div class="app-photo-upload__actions">
        <input
          ref="inputRef"
          type="file"
          class="app-photo-upload__input"
          :accept="ACCEPTED_IMAGE_ACCEPT"
          :disabled="disabled || uploading || removing"
          @change="onSelected"
        >
        <Button
          type="button"
          label="Choisir une image"
          icon="pi pi-upload"
          outlined
          size="small"
          :loading="uploading"
          :disabled="disabled || uploading || removing"
          @click="inputRef?.click()"
        />
        <Button
          v-if="previewUrl"
          type="button"
          label="Supprimer"
          icon="pi pi-trash"
          severity="danger"
          text
          size="small"
          :loading="removing"
          :disabled="disabled || uploading || removing"
          @click="onRemove"
        />
        <small class="app-photo-upload__hint">JPEG, PNG, WebP ou GIF — 2 Mo max.</small>
      </div>
    </div>
    <Message v-if="displayError" severity="error" :closable="false" class="app-photo-upload__error">
      {{ displayError }}
    </Message>
  </div>
</template>

<style scoped>
.app-photo-upload__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.85rem;
  align-items: center;
}

.app-photo-upload__preview {
  display: grid;
  place-items: center;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: 999px;
  overflow: hidden;
  border: 1px dashed var(--p-content-border-color, #cbd5e1);
  background: color-mix(in srgb, var(--layout-surface-muted, #f8fafc) 90%, transparent);
}

.app-photo-upload__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.app-photo-upload__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.5rem;
  align-items: center;
  flex: 1;
  min-width: 12rem;
}

.app-photo-upload__input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  overflow: hidden;
}

.app-photo-upload__hint {
  flex-basis: 100%;
  color: var(--layout-text-muted, #64748b);
  font-size: 0.75rem;
}

.app-photo-upload__error {
  margin-top: 0.5rem;
}

.field label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}
</style>
