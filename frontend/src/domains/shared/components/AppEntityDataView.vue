<script setup>
import Tag from 'primevue/tag'
import AppTableActionsMenu from '@/domains/shared/components/AppTableActionsMenu.vue'

defineProps({
  items: {
    type: Array,
    required: true
  },
  /** (item) => string */
  titleOf: {
    type: Function,
    required: true
  },
  /** (item) => string | null */
  subtitleOf: {
    type: Function,
    default: null
  },
  /** (item) => string | null */
  codeOf: {
    type: Function,
    default: null
  },
  /** (item) => { value, severity } | null */
  statusOf: {
    type: Function,
    default: null
  },
  /** (item) => string | null — extra meta line */
  metaOf: {
    type: Function,
    default: null
  },
  /** (item) => action[] for AppTableActionsMenu */
  actionsOf: {
    type: Function,
    default: null
  },
  /** (item) => event bindings object (context menu / long-press) */
  rowBindingsOf: {
    type: Function,
    default: null
  },
  dataKey: {
    type: String,
    default: 'id'
  }
})

defineEmits(['select'])
</script>

<template>
  <div class="app-entity-dataview" role="list">
    <article
      v-for="item in items"
      :key="item[dataKey]"
      class="app-entity-card"
      role="listitem"
      tabindex="0"
      v-on="rowBindingsOf?.(item) ?? {}"
      @click="$emit('select', item)"
      @keydown.enter.prevent="$emit('select', item)"
    >
      <div class="app-entity-card__row">
        <div style="min-width: 0; flex: 1">
          <p v-if="codeOf?.(item)" class="app-entity-card__code">{{ codeOf(item) }}</p>
          <h3 class="app-entity-card__title">{{ titleOf(item) }}</h3>
          <p v-if="subtitleOf?.(item)" class="app-entity-card__subtitle">{{ subtitleOf(item) }}</p>
        </div>
        <div @click.stop>
          <AppTableActionsMenu
            v-if="actionsOf?.(item)?.length"
            :actions="actionsOf(item)"
            aria-label="Actions"
          />
        </div>
      </div>

      <div v-if="statusOf?.(item) || metaOf?.(item)" class="app-entity-card__meta-row">
        <Tag
          v-if="statusOf?.(item)"
          :value="statusOf(item).value"
          :severity="statusOf(item).severity || 'secondary'"
          rounded
        />
        <span v-if="metaOf?.(item)" class="app-entity-card__meta">{{ metaOf(item) }}</span>
        <slot name="meta" :item="item" />
      </div>

      <slot name="footer" :item="item" />
    </article>
  </div>
</template>
