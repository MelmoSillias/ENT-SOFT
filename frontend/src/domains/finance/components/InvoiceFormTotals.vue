<script setup>
import { computed } from 'vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { formatMontant } from '@/domains/shared/utils/formatMontant'

const props = defineProps({ form: { type: Object, required: true } })
// Ignore les lignes sans désignation, comme le fait la sauvegarde du formulaire.
const subtotal = computed(() => (props.form.lines ?? [])
  .filter((line) => String(line.description || '').trim())
  .reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unitPrice || 0), 0))
const vatAmount = computed(() => props.form.vatEnabled
  ? Math.round((subtotal.value * Number(props.form.vatRate || 0) / 100 + Number.EPSILON) * 100) / 100
  : 0)
const total = computed(() => subtotal.value + vatAmount.value)
const money = (amount) => formatMontant(amount, DEVISE_APP)
</script>

<template>
  <dl class="invoice-totals" aria-live="polite" aria-atomic="true">
    <div><dt>Total HT</dt><dd>{{ money(subtotal) }}</dd></div>
    <div><dt>{{ form.vatEnabled ? `TVA (${form.vatRate ?? 0} %)` : 'TVA non appliquée' }}</dt><dd>{{ money(vatAmount) }}</dd></div>
    <div class="invoice-totals__grand"><dt>Total TTC</dt><dd>{{ money(total) }}</dd></div>
  </dl>
</template>

<style scoped>
.invoice-totals { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; margin: 0; width: 100%; padding: 0.75rem 0; border-top: 1px solid var(--layout-panel-border); text-align: left; }
.invoice-totals dt { font-size: 0.75rem; color: var(--layout-text-muted); }
.invoice-totals dd { margin: 0.25rem 0 0; font-weight: 650; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.invoice-totals__grand dd { color: var(--p-primary-color); }
</style>
