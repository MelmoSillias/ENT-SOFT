<script setup>
import { computed } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { formatDateFr, projectSiteStatusLabel, projectSiteStatusSeverity } from '@/domains/shared/utils/entLabels'

const props = defineProps({
  sites: { type: Array, default: () => [] },
  sitesInformations: { type: Array, default: () => [] },
  lots: { type: Array, default: () => [] },
})

const COMMENT_KEYS = new Set(['comment', 'remarques'])

const infoColumns = computed(() =>
  (props.sitesInformations ?? []).filter((col) => col?.key && !COMMENT_KEYS.has(col.key)),
)

const groupedSites = computed(() => {
  if (!props.lots?.length) {
    return [{ lot: null, sites: props.sites ?? [] }]
  }

  const lotOrder = props.lots.map((lot) => lot.code)
  const byLot = new Map(lotOrder.map((code) => [code, []]))
  const unassigned = []

  for (const site of props.sites ?? []) {
    const code = site.lotCode
    if (code && byLot.has(code)) {
      byLot.get(code).push(site)
    } else {
      unassigned.push(site)
    }
  }

  const groups = props.lots.map((lot) => ({
    lot,
    sites: byLot.get(lot.code) ?? [],
  }))

  if (unassigned.length) {
    groups.push({ lot: { code: '__none', title: 'Sans lot' }, sites: unassigned })
  }

  return groups
})

function cellValue(site, key) {
  const raw = site.informationsValues?.[key]
  if (raw === null || raw === undefined || raw === '') return '—'
  if (typeof raw === 'boolean') return raw ? 'Oui' : 'Non'
  if (key.endsWith('_date') || key === 'start_date' || key === 'end_date') {
    return formatDateFr(raw)
  }
  return String(raw)
}

function lotLabel(lot) {
  if (!lot) return ''
  if (lot.code === '__none') return lot.title
  return lot.title ? `${lot.code} — ${lot.title}` : lot.code
}
</script>

<template>
  <div v-if="!sites?.length" class="project-sites__empty">Aucun site associé.</div>

  <div v-else class="project-sites">
    <section
      v-for="group in groupedSites"
      :key="group.lot?.code ?? 'all'"
      class="project-sites__group"
    >
      <h3 v-if="group.lot" class="project-sites__lot-title">{{ lotLabel(group.lot) }}</h3>

      <DataTable
        :value="group.sites"
        striped-rows
        scrollable
        scroll-height="flex"
        class="project-sites__table"
      >
        <Column field="siteCode" header="Code site" frozen style="min-width: 7rem" />
        <Column field="siteTitle" header="Nom du site" frozen style="min-width: 12rem" />

        <Column
          v-for="col in infoColumns"
          :key="col.key"
          :header="col.label"
          style="min-width: 8rem"
        >
          <template #body="{ data }">{{ cellValue(data, col.key) }}</template>
        </Column>

        <Column header="Statut" style="min-width: 7rem">
          <template #body="{ data }">
            <Tag
              :value="projectSiteStatusLabel(data.status)"
              :severity="projectSiteStatusSeverity(data.status)"
            />
          </template>
        </Column>

        <Column header="Commentaires" style="min-width: 14rem">
          <template #body="{ data }">
            <span class="project-sites__comment">{{ data.comment || '—' }}</span>
          </template>
        </Column>

        <Column v-if="sites.some((s) => s.technicianName)" header="Technicien" style="min-width: 9rem">
          <template #body="{ data }">{{ data.technicianName || '—' }}</template>
        </Column>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.project-sites {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.project-sites__group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.project-sites__lot-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--layout-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.project-sites__comment {
  white-space: pre-wrap;
  word-break: break-word;
}

.project-sites__empty {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
