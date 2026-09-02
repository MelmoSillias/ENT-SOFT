<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const items = ref([])
onMounted(async () => {
  const { data } = await api.get('/pays')
  items.value = data.items ?? data
})
</script>

<template>
  <div>
    <h1>Pays</h1>
    <DataTable :value="items" paginator :rows="10">
      <Column field="code" header="Code" />
      <Column field="nom" header="Nom" />
      <Column field="indicatifTelephonique" header="Indicatif" />
      <Column field="deviseCode" header="Devise" />
    </DataTable>
  </div>
</template>
