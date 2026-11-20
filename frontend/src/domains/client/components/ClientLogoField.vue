<script setup>
import { ref, useId } from 'vue'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { uploadClientLogo, deleteClientLogo } from '@/domains/client/services/clientService'

const props = defineProps({
  client: { type: Object, required: true },
  editable: { type: Boolean, default: false },
})
const emit = defineEmits(['updated'])
const inputId = useId()
const input = ref(null)
const busy = ref(false)
const error = ref(null)

async function changeLogo(file = null) {
  if (!props.editable || busy.value) return
  error.value = null
  if (file && (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
    error.value = 'Choisissez une image JPEG, PNG, WebP ou GIF de 2 Mo maximum.'
    return
  }
  busy.value = true
  try {
    const client = file ? await uploadClientLogo(props.client.id, file) : await deleteClientLogo(props.client.id)
    emit('updated', client)
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de modifier le logo du client.'
  } finally {
    busy.value = false
  }
}

function selectFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (file) return changeLogo(file)
}
</script>

<template>
  <div class="client-logo" :aria-busy="busy">
    <label :for="editable ? inputId : undefined">Logo du client</label>
    <div class="client-logo__row">
      <img v-if="client.logoUrl" :src="client.logoUrl" :alt="`Logo de ${client.title}`" class="client-logo__image">
      <span v-else>Aucun logo</span>
      <template v-if="editable">
        <input :id="inputId" ref="input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="busy" class="client-logo__input" @change="selectFile">
        <Button type="button" :label="client.logoUrl ? 'Remplacer le logo' : 'Ajouter un logo'" icon="pi pi-upload" outlined size="small" :loading="busy" :disabled="busy" @click="input?.click()" />
        <Button v-if="client.logoUrl" type="button" label="Supprimer le logo" icon="pi pi-trash" severity="danger" text size="small" :disabled="busy" @click="changeLogo()" />
        <small>JPEG, PNG, WebP ou GIF · 2 Mo max. · 4096 × 4096 px max.</small>
      </template>
    </div>
    <Message v-if="error" severity="error" :closable="false" role="alert">{{ error }}</Message>
  </div>
</template>

<style scoped>
.client-logo { margin-bottom: 1.5rem; }
.client-logo > label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
.client-logo__row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.client-logo__image { width: 8rem; height: 5rem; object-fit: contain; }
.client-logo__row small { flex-basis: 100%; color: var(--layout-text-muted); }
.client-logo__input { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
</style>
