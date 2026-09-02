<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'

const items = ref([])
onMounted(async () => {
  const { data } = await api.get('/notifications')
  items.value = data.items ?? data
})
</script>

<template>
  <div>
    <h1>Notifications</h1>
    <DataTable :value="items" paginator :rows="10">
      <Column field="canal" header="Canal" />
      <Column field="destinataire" header="Destinataire" />
      <Column field="statut" header="Statut">
        <template #body="{ data }"><Tag :value="data.statut" /></template>
      </Column>
      <Column field="tentatives" header="Tentatives" />
    </DataTable>
  </div>
</template>
