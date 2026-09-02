<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const items = ref([])
onMounted(async () => {
  const { data } = await api.get('/settings')
  items.value = data.items ?? data
})
</script>

<template>
  <div>
    <h1>Paramètres</h1>
    <DataTable :value="items">
      <Column field="cle" header="Clé" />
      <Column field="valeur" header="Valeur" />
      <Column field="type" header="Type" />
      <Column field="description" header="Description" />
    </DataTable>
  </div>
</template>
