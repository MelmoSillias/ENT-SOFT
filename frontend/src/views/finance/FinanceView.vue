<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import InvoiceListView from '@/views/finance/InvoiceListView.vue'
import TransactionListPanel from '@/views/finance/TransactionListPanel.vue'

const route = useRoute()
const router = useRouter()
const activeTab = ref('invoices')

function syncFromQuery() {
  const tab = route.query.tab
  if (tab === 'expenses' || tab === 'transactions' || tab === 'invoices') {
    activeTab.value = tab
  } else {
    activeTab.value = 'invoices'
  }
}

watch(() => route.query.tab, syncFromQuery, { immediate: true })
watch(activeTab, (tab) => {
  if (route.query.tab !== tab) {
    router.replace({ query: { ...route.query, tab } })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #content>
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="invoices">Factures</Tab>
            <Tab value="expenses">Dépenses</Tab>
            <Tab value="transactions">Transactions</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="invoices">
              <InvoiceListView embedded />
            </TabPanel>
            <TabPanel value="expenses">
              <TransactionListPanel expense-only title="Dépenses" create-label="Nouvelle dépense" />
            </TabPanel>
            <TabPanel value="transactions">
              <TransactionListPanel title="Transactions" create-label="Nouvelle transaction" />
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </section>
</template>
