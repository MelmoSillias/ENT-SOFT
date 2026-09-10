<script setup>
import { computed, ref, watch } from 'vue'
import Tag from 'primevue/tag'
import AppTableActionsMenu from '@/domains/shared/components/AppTableActionsMenu.vue'
import AppPersonAvatar from '@/domains/shared/components/AppPersonAvatar.vue'
import AppEntityPaginator from '@/domains/shared/components/AppEntityPaginator.vue'
import { useClientPagination } from '@/domains/shared/composables/useClientPagination'

const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  /** (item) => string */
  titleOf: {
    type: Function,
    required: true,
  },
  /** (item) => string | null */
  subtitleOf: {
    type: Function,
    default: null,
  },
  /** (item) => string | null */
  codeOf: {
    type: Function,
    default: null,
  },
  /** (item) => { value, severity } | null */
  statusOf: {
    type: Function,
    default: null,
  },
  /** (item) => string | null — extra meta line */
  metaOf: {
    type: Function,
    default: null,
  },
  /** (item) => { name: string, photoUrl?: string|null } | null */
  avatarOf: {
    type: Function,
    default: null,
  },
  /** (item) => action[] for AppTableActionsMenu */
  actionsOf: {
    type: Function,
    default: null,
  },
  /** (item) => event bindings object (context menu / long-press) */
  rowBindingsOf: {
    type: Function,
    default: null,
  },
  dataKey: {
    type: String,
    default: 'id',
  },
  /** Page size from table settings; enables client pagination when set and not lazy */
  rows: {
    type: Number,
    default: null,
  },
  /** Show absolute rank (#) on each card */
  showIndex: {
    type: Boolean,
    default: true,
  },
  /** Server-side / controlled pagination */
  lazy: {
    type: Boolean,
    default: false,
  },
  first: {
    type: Number,
    default: 0,
  },
  totalRecords: {
    type: Number,
    default: null,
  },
})

const emit = defineEmits(['select', 'page', 'update:first'])

const {
  first: clientFirst,
  pageItems: clientPageItems,
  totalRecords: clientTotal,
  onPage: onClientPage,
  rankOf: clientRankOf,
} = useClientPagination(
  () => props.items,
  () => (props.lazy ? null : props.rows),
)

const localFirst = ref(props.first)

watch(
  () => props.first,
  (value) => {
    localFirst.value = value
  },
)

const pageSize = computed(() => {
  const value = Number(props.rows)
  return Number.isFinite(value) && value > 0 ? value : null
})

const displayItems = computed(() => (props.lazy ? props.items : clientPageItems.value))

const paginatorFirst = computed(() => (props.lazy ? localFirst.value : clientFirst.value))

const paginatorTotal = computed(() => {
  if (props.lazy) {
    return props.totalRecords ?? props.items.length
  }
  return clientTotal.value
})

const showPaginator = computed(() => Boolean(pageSize.value) && paginatorTotal.value > 0)

function rankOf(indexOnPage) {
  if (props.lazy) {
    return localFirst.value + indexOnPage + 1
  }
  return clientRankOf(indexOnPage)
}

function onPage(event) {
  if (props.lazy) {
    localFirst.value = event.first
    emit('update:first', event.first)
    emit('page', event)
    return
  }
  onClientPage(event)
}
</script>

<template>
  <div class="app-entity-dataview" role="list">
    <AppEntityPaginator
      v-if="showPaginator"
      position="top"
      :rows="pageSize"
      :first="paginatorFirst"
      :total-records="paginatorTotal"
      @page="onPage"
    />

    <article
      v-for="(item, index) in displayItems"
      :key="item[dataKey]"
      class="app-entity-card"
      role="listitem"
      tabindex="0"
      v-on="rowBindingsOf?.(item) ?? {}"
      @click="$emit('select', item)"
      @keydown.enter.prevent="$emit('select', item)"
    >
      <div class="app-entity-card__row">
        <span v-if="showIndex" class="app-entity-card__index" aria-hidden="true">{{ rankOf(index) }}</span>
        <AppPersonAvatar
          v-if="avatarOf?.(item)"
          :name="avatarOf(item).name"
          :photo-url="avatarOf(item).photoUrl"
          size="normal"
          class="app-entity-card__avatar"
        />
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

    <AppEntityPaginator
      v-if="showPaginator"
      position="bottom"
      :rows="pageSize"
      :first="paginatorFirst"
      :total-records="paginatorTotal"
      @page="onPage"
    />
  </div>
</template>
