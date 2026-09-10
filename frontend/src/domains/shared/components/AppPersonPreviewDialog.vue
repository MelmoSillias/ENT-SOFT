<script setup>
import { computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import AppDetailInfoList from '@/domains/shared/components/AppDetailInfoList.vue'
import {
  personDisplayName,
  personInitials,
  personPhotoUrl,
  personPreviewItems,
  resolvePersonKind,
} from '@/domains/shared/utils/personDisplay'

const props = defineProps({
  visible: { type: Boolean, default: false },
  person: { type: Object, default: null },
  /** 'employee' | 'prestataire' | 'user' | 'auto' */
  kind: { type: String, default: 'auto' },
})

const emit = defineEmits(['update:visible'])

const displayName = computed(() => personDisplayName(props.person, '—'))
const photoUrl = computed(() => personPhotoUrl(props.person) || undefined)
const initials = computed(() => personInitials(displayName.value))
const resolvedKind = computed(() => resolvePersonKind(props.kind, props.person))
const infoItems = computed(() => personPreviewItems(props.person, resolvedKind.value))

const kindLabel = computed(() => {
  switch (resolvedKind.value) {
    case 'user':
      return 'Utilisateur'
    case 'prestataire':
      return 'Prestataire'
    default:
      return 'Employé'
  }
})

function close() {
  emit('update:visible', false)
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    dismissable-mask
    class="app-person-preview-dialog"
    :style="{ width: 'min(420px, 95vw)' }"
    @update:visible="emit('update:visible', $event)"
  >
    <template #header>
      <div class="app-person-preview-dialog__header">
        <span class="app-person-preview-dialog__eyebrow">{{ kindLabel }}</span>
        <h2 class="app-person-preview-dialog__title">{{ displayName }}</h2>
      </div>
    </template>

    <div class="app-person-preview-dialog__body">
      <div class="app-person-preview-dialog__photo">
        <Avatar
          :image="photoUrl"
          :label="photoUrl ? undefined : initials"
          shape="circle"
          size="xlarge"
          class="app-person-preview-dialog__avatar"
        />
      </div>

      <AppDetailInfoList v-if="infoItems.length" :items="infoItems" />
      <p v-else class="app-person-preview-dialog__empty">Aucune information complémentaire.</p>
    </div>

    <template #footer>
      <Button label="Fermer" icon="pi pi-times" severity="secondary" text @click="close" />
    </template>
  </Dialog>
</template>

<style scoped>
.app-person-preview-dialog__header {
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}

.app-person-preview-dialog__eyebrow {
  font-size: 0.7rem;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--layout-text-muted, #64748b);
}

.app-person-preview-dialog__title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.25;
  color: var(--layout-text-color, inherit);
  word-break: break-word;
}

.app-person-preview-dialog__body {
  display: grid;
  gap: 1rem;
}

.app-person-preview-dialog__photo {
  display: grid;
  place-items: center;
  padding: 0.5rem 0 0.25rem;
}

.app-person-preview-dialog__avatar {
  width: 9.5rem !important;
  height: 9.5rem !important;
  font-size: 2.4rem !important;
  background: color-mix(in srgb, var(--p-primary-color, #0ea5e9) 16%, transparent);
  color: var(--p-primary-color, #0284c7);
  font-weight: 700;
}

.app-person-preview-dialog__avatar :deep(img) {
  object-fit: cover;
}

.app-person-preview-dialog__empty {
  margin: 0;
  text-align: center;
  color: var(--layout-text-muted, #64748b);
  font-size: 0.9rem;
}
</style>
