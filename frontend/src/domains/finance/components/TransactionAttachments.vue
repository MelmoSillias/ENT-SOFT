<script setup>
import { onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import { listDocuments, uploadDocument, deleteDocument } from '@/domains/document/services/documentService'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const props = defineProps({
  ownerId: { type: String, default: null },
  ownerType: { type: String, default: 'financial_transaction' },
})

const toast = useAppToast()
const { hasPermission } = usePermissions()
const items = ref([])
const loading = ref(false)
const uploading = ref(false)
const fileInput = ref(null)

const canUpload = () => hasPermission('document.documents.upload')
const canDelete = () => hasPermission('document.documents.delete')

async function load() {
  if (!props.ownerId) {
    items.value = []
    return
  }
  loading.value = true
  try {
    items.value = await listDocuments({ ownerType: props.ownerType, ownerId: props.ownerId })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Pièces jointes', detail: e.response?.data?.error || 'Chargement impossible.' })
  } finally {
    loading.value = false
  }
}

async function onFile(event) {
  const file = event.target?.files?.[0]
  if (!file || !props.ownerId) return
  uploading.value = true
  try {
    const body = new FormData()
    body.append('file', file)
    body.append('title', file.name)
    body.append('ownerType', props.ownerType)
    body.append('ownerId', props.ownerId)
    await uploadDocument(body)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Pièces jointes', detail: e.response?.data?.error || 'Envoi impossible.' })
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function remove(doc) {
  try {
    await deleteDocument(doc.id)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Pièces jointes', detail: e.response?.data?.error || 'Suppression impossible.' })
  }
}

watch(() => props.ownerId, load)
onMounted(load)
</script>

<template>
  <div class="tx-attachments">
    <div class="tx-attachments__header">
      <span>Pièces jointes</span>
      <template v-if="ownerId && canUpload()">
        <input ref="fileInput" type="file" class="tx-attachments__input" @change="onFile">
        <Button label="Ajouter" icon="pi pi-paperclip" size="small" outlined :loading="uploading" @click="fileInput?.click()" />
      </template>
    </div>
    <p v-if="!ownerId" class="tx-attachments__hint">Enregistrez d'abord la transaction pour joindre des fichiers.</p>
    <p v-else-if="loading" class="tx-attachments__hint">Chargement…</p>
    <ul v-else-if="items.length" class="tx-attachments__list">
      <li v-for="doc in items" :key="doc.id">
        <a :href="doc.filePath" target="_blank" rel="noopener">{{ doc.title || doc.fileName }}</a>
        <Button v-if="canDelete()" icon="pi pi-times" text rounded size="small" severity="danger" @click="remove(doc)" />
      </li>
    </ul>
    <p v-else class="tx-attachments__hint">Aucune pièce jointe.</p>
  </div>
</template>

<style scoped>
.tx-attachments__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.85rem;
  margin-bottom: 0.4rem;
}

.tx-attachments__input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}

.tx-attachments__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.tx-attachments__list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.35rem;
}

.tx-attachments__hint {
  margin: 0;
  color: var(--layout-text-muted);
  font-size: 0.8rem;
}
</style>
