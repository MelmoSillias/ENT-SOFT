<script setup>
defineProps({
  /** @type {{ key: string, label: string, icon?: string, value?: string|number|null, full?: boolean }[]} */
  items: {
    type: Array,
    required: true,
  },
})
</script>

<template>
  <dl class="app-detail-info">
    <div
      v-for="item in items"
      :key="item.key"
      class="app-detail-info__row"
      :class="{ 'is-full': item.full }"
    >
      <span v-if="item.icon" class="app-detail-info__icon" aria-hidden="true">
        <i :class="item.icon" />
      </span>
      <div class="app-detail-info__body">
        <dt>{{ item.label }}</dt>
        <dd>
          <slot :name="item.key" :item="item">
            {{ item.value != null && item.value !== '' ? item.value : '—' }}
          </slot>
        </dd>
      </div>
    </div>
  </dl>
</template>

<style scoped>
.app-detail-info {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1px;
  margin: 0;
  border: 1px solid var(--layout-panel-border);
  border-radius: var(--layout-radius-sm, 0.5rem);
  overflow: hidden;
  background: var(--layout-panel-border);
}

.app-detail-info__row {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  padding: 0.8rem 0.9rem;
  min-width: 0;
  background: color-mix(in srgb, var(--layout-panel-bg) 96%, white);
}

.app-detail-info__row.is-full {
  grid-column: 1 / -1;
}

.app-detail-info__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  background: var(--layout-accent-soft);
  color: var(--layout-accent-strong);
  font-size: 0.8rem;
}

.app-detail-info__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.2rem;
}

.app-detail-info__body dt {
  margin: 0;
  font-size: 0.68rem;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--layout-text-muted);
  line-height: 1.2;
}

.app-detail-info__body dd {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.4;
  color: var(--layout-text-color);
  word-break: break-word;
}

@media (max-width: 640px) {
  .app-detail-info {
    grid-template-columns: 1fr;
  }

  .app-detail-info__row {
    padding: 0.7rem 0.8rem;
  }

  .app-detail-info__icon {
    width: 1.85rem;
    height: 1.85rem;
    font-size: 0.75rem;
  }

  .app-detail-info__body dt {
    font-size: 0.65rem;
  }

  .app-detail-info__body dd {
    font-size: 0.85rem;
  }
}
</style>
