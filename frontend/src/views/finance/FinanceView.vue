<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import InvoiceListView from '@/views/finance/InvoiceListView.vue'
import TransactionListPanel from '@/views/finance/TransactionListPanel.vue'

const route = useRoute()
const router = useRouter()
const { isAppMobile } = useAppMobileLayout()
const activeTab = ref('invoices')

const tabItems = [
  { value: 'invoices', label: 'Factures', shortLabel: 'Factures' },
  { value: 'expenses', label: 'Dépenses', shortLabel: 'Dépenses' },
  { value: 'transactions', label: 'Transactions', shortLabel: 'Transac.' },
]

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
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="activeTab"
          :items="tabItems"
        />
        <Tabs v-else v-model:value="activeTab">
          <TabList>
            <Tab value="invoices">Factures</Tab>
            <Tab value="expenses">Dépenses</Tab>
            <Tab value="transactions">Transactions</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="invoices" />
            <TabPanel value="expenses" />
            <TabPanel value="transactions" />
          </TabPanels>
        </Tabs>

        <div v-if="activeTab === 'invoices'">
          <InvoiceListView embedded />
        </div>
        <div v-else-if="activeTab === 'expenses'">
          <TransactionListPanel
            expense-only
            title="Dépenses"
            create-label="Nouvelle dépense"
          />
        </div>
        <div v-else-if="activeTab === 'transactions'">
          <TransactionListPanel
            title="Transactions"
            create-label="Nouvelle transaction"
            show-stats
          />
        </div>
      </template>
    </Card>
  </section>
</template>
