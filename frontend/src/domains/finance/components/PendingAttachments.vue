<script setup>
import { ref } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'

const model = defineModel({ type: Array, default: () => [] })
const fileInput = ref(null)

function addFiles(event) {
  const files = [...(event.target?.files || [])]
  for (const file of files) {
    model.value = [
      ...model.value,
      {
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        file,
        displayName: file.name,
      },
    ]
  }
  if (fileInput.value) fileInput.value.value = ''
}

function removeAt(id) {
  model.value = model.value.filter((item) => item.id !== id)
}
</script>

<template>
  <div class="pending-attachments">
    <div class="pending-attachments__header">
      <span>Pièces jointes</span>
      <input ref="fileInput" type="file" multiple class="pending-attachments__input" @change="addFiles">
      <Button label="Ajouter pièces jointes" icon="pi pi-paperclip" size="small" outlined @click="fileInput?.click()" />
    </div>
    <ul v-if="model.length" class="pending-attachments__list">
      <li v-for="item in model" :key="item.id" class="pending-attachments__item">
        <InputText v-model="item.displayName" fluid />
        <Button icon="pi pi-times" text rounded severity="danger" @click="removeAt(item.id)" />
      </li>
    </ul>
    <p v-else class="pending-attachments__hint">Ajoutez des fichiers ; ils seront envoyés après l’enregistrement.</p>
  </div>
</template>

<style scoped>
.pending-attachments {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 0.75rem;
}
.pending-attachments__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.pending-attachments__input { display: none; }
.pending-attachments__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.pending-attachments__item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.pending-attachments__hint {
  margin: 0;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}
</style>
