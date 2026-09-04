<script setup>
import { ref } from 'vue'
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import ToggleSwitch from 'primevue/toggleswitch'

const props = defineProps({
  /** Columns config: { key, label }[] */
  columns: { type: Array, default: () => [] },
  visibleColKeys: { type: Array, default: () => [] },
  rows: { type: Number, default: 10 },
  rowOptions: { type: Array, default: () => [5, 10, 25, 50, 100] },
  showIndex: { type: Boolean, default: true },
  sortField: { type: [String, null], default: null },
  sortOrder: { type: Number, default: 1 },
  sortOptions: { type: Array, default: () => [] },
  showColumns: { type: Boolean, default: true },
  showRows: { type: Boolean, default: true },
  showIndexToggle: { type: Boolean, default: true },
  showSort: { type: Boolean, default: true },
  tooltip: { type: String, default: 'Paramètres / filtres' },
})

const emit = defineEmits([
  'update:visibleColKeys',
  'update:rows',
  'update:showIndex',
  'update:sortField',
  'update:sortOrder',
  'toggle-col',
])

const panel = ref()

const sortOrderOptions = [
  { label: 'Croiss.', value: 1 },
  { label: 'Décrois.', value: -1 },
]

function toggle(event) {
  panel.value?.toggle(event)
}

function onColChange(key, enabled) {
  emit('toggle-col', key, enabled)
  if (enabled) {
    if (!props.visibleColKeys.includes(key)) {
      emit('update:visibleColKeys', [...props.visibleColKeys, key])
    }
  } else {
    emit(
      'update:visibleColKeys',
      props.visibleColKeys.filter((k) => k !== key),
    )
  }
}

defineExpose({ toggle, hide: () => panel.value?.hide() })
</script>

<template>
  <Button
    icon="pi pi-filter"
    size="small"
    severity="secondary"
    outlined
    v-tooltip.top="tooltip"
    aria-label="Paramètres et filtres"
    @click="toggle"
  />
  <Popover ref="panel" class="app-table-settings-panel">
    <div class="app-table-settings">
      <slot name="filters" />

      <template v-if="showRows">
        <p class="app-table-settings__title">Lignes par page</p>
        <div class="app-table-settings__control">
          <Select
            :model-value="rows"
            :options="rowOptions"
            fluid
            size="small"
            append-to="body"
            @update:model-value="emit('update:rows', $event)"
          />
        </div>
      </template>

      <template v-if="showIndexToggle">
        <p class="app-table-settings__title">Numérotation</p>
        <label class="app-table-settings__row">
          <ToggleSwitch
            :model-value="showIndex"
            @update:model-value="emit('update:showIndex', $event)"
          />
          <span>Afficher la colonne #</span>
        </label>
      </template>

      <template v-if="showSort && sortOptions.length">
        <p class="app-table-settings__title">Tri</p>
        <div class="app-table-settings__sort">
          <div class="app-table-settings__control app-table-settings__sort-field">
            <Select
              :model-value="sortField"
              :options="sortOptions"
              option-label="label"
              option-value="value"
              placeholder="Colonne"
              show-clear
              fluid
              size="small"
              append-to="body"
              @update:model-value="emit('update:sortField', $event)"
            />
          </div>
          <SelectButton
            :model-value="sortOrder"
            :options="sortOrderOptions"
            option-label="label"
            option-value="value"
            :allow-empty="false"
            :disabled="!sortField"
            class="app-table-settings__sort-order"
            aria-label="Ordre de tri"
            @update:model-value="emit('update:sortOrder', $event)"
          />
        </div>
      </template>

      <template v-if="showColumns && columns.length">
        <p class="app-table-settings__title">Colonnes visibles</p>
        <div class="app-table-settings__cols">
          <label
            v-for="col in columns"
            :key="col.key"
            class="app-table-settings__col-item"
          >
            <Checkbox
              :model-value="visibleColKeys.includes(col.key)"
              binary
              @update:model-value="(v) => onColChange(col.key, v)"
            />
            <span>{{ col.label }}</span>
          </label>
        </div>
      </template>
    </div>
  </Popover>
</template>

<style scoped>
.app-table-settings {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: min(18rem, 85vw);
  max-width: 22rem;
  box-sizing: border-box;
}

.app-table-settings__title {
  margin: 0.65rem 0 0.35rem;
  font-size: 0.72rem;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.app-table-settings__title:first-child {
  margin-top: 0;
}

.app-table-settings__row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  font-size: 0.85rem;
}

.app-table-settings__control {
  width: 100%;
  min-width: 0;
}

.app-table-settings__control :deep(.p-select) {
  width: 100%;
}

.app-table-settings__sort {
  display: flex;
  align-items: stretch;
  gap: 0.45rem;
  min-width: 0;
}

.app-table-settings__sort-field {
  flex: 1 1 auto;
  min-width: 0;
}

.app-table-settings__sort-order {
  flex: 0 0 auto;
  align-self: stretch;
}

.app-table-settings__sort-order :deep(.p-togglebutton),
.app-table-settings__sort-order :deep(.p-selectbutton .p-togglebutton) {
  font-size: 0.72rem;
  padding-inline: 0.45rem;
  white-space: nowrap;
}

.app-table-settings__cols {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.app-table-settings__col-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  cursor: pointer;
}
</style>

<style>
.app-table-settings-panel.p-popover {
  background: var(--layout-panel-bg);
  border-color: var(--layout-panel-border);
  color: var(--layout-text-color);
  max-width: min(22rem, calc(100vw - 1.5rem));
}

.app-table-settings-panel.p-popover .p-popover-content {
  background: transparent;
  color: inherit;
  overflow: visible;
}

.app-table-settings__title {
  margin: 0.65rem 0 0.35rem;
  font-size: 0.72rem;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.app-table-settings__title:first-child {
  margin-top: 0;
}

.app-table-settings__mb {
  margin-bottom: 0.35rem;
  width: 100%;
  min-width: 0;
}

.app-table-settings__mb.p-select,
.app-table-settings .p-select {
  width: 100%;
}
</style>
