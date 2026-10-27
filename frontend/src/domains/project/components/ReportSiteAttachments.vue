<script setup>
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'

const model = defineModel({ type: Array, default: () => [] })
const emit = defineEmits(['remove-existing'])
const input = ref(null)

function add(event) {
  const files = [...(event.target?.files || [])]
  model.value = [
    ...model.value,
    ...files.map((file) => ({
      clientId: crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`,
      file,
      title: file.name,
      description: '',
      previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
      pending: true,
    })),
  ]
  if (input.value) input.value.value = ''
}

function remove(item) {
  if (item.previewUrl?.startsWith('blob:')) URL.revokeObjectURL(item.previewUrl)
  if (item.id && !item.pending) emit('remove-existing', item.id)
  model.value = model.value.filter((entry) => entry !== item)
}
</script>

<template>
  <div class="report-files">
    <div class="report-files__header">
      <strong>Images et fichiers</strong>
      <input ref="input" type="file" multiple class="report-files__input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" @change="add">
      <Button label="Ajouter" icon="pi pi-paperclip" size="small" outlined @click="input?.click()" />
    </div>
    <div v-if="model.length" class="report-files__list">
      <article v-for="item in model" :key="item.id || item.clientId" class="report-files__item">
        <img v-if="item.previewUrl || item.url" :src="item.previewUrl || item.url" alt="" class="report-files__preview">
        <i v-else class="pi pi-file report-files__file-icon" aria-hidden="true" />
        <div class="report-files__fields">
          <InputText v-model="item.title" placeholder="Nom du fichier" fluid />
          <InputText v-model="item.description" placeholder="Description ou légende" fluid />
        </div>
        <Button icon="pi pi-times" text rounded severity="danger" aria-label="Retirer" @click="remove(item)" />
      </article>
    </div>
    <small v-else>Aucun fichier. Les images seront intégrées aux exports PDF et Word.</small>
  </div>
</template>

<style scoped>
.report-files {
  display: grid;
  gap: 0.65rem;
}

.report-files__header,
.report-files__item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.report-files__header {
  justify-content: space-between;
}

.report-files__input {
  display: none;
}

.report-files__list {
  display: grid;
  gap: 0.5rem;
}

.report-files__item {
  padding: 0.55rem;
  border: 1px solid var(--layout-panel-border);
  border-radius: var(--layout-radius-md, 0.65rem);
}

.report-files__preview {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 0.45rem;
  object-fit: cover;
}

.report-files__file-icon {
  width: 3.5rem;
  text-align: center;
  font-size: 1.5rem;
}

.report-files__fields {
  display: grid;
  grid-template-columns: minmax(10rem, 0.8fr) minmax(12rem, 1.2fr);
  gap: 0.5rem;
  flex: 1;
}

small {
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

@media (max-width: 720px) {
  .report-files__fields {
    grid-template-columns: 1fr;
  }
}
</style>
