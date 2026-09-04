<script setup>
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

defineProps({
  title: {
    type: String,
    required: true
  },
  countLabel: {
    type: String,
    default: ''
  },
  showCount: {
    type: Boolean,
    default: true
  },
  showSearch: {
    type: Boolean,
    default: false
  },
  searchTerm: {
    type: String,
    default: ''
  },
  searchPlaceholder: {
    type: String,
    default: 'Rechercher…'
  },
  showReload: {
    type: Boolean,
    default: true
  },
  reloading: {
    type: Boolean,
    default: false
  },
  showCreate: {
    type: Boolean,
    default: false
  },
  createLabel: {
    type: String,
    default: ''
  },
  createAsFab: {
    type: Boolean,
    default: false
  }
})

defineEmits(['update:searchTerm', 'reload', 'create'])
</script>

<template>
  <div class="app-sticky-page-header">
    <div class="app-sticky-page-header__row">
      <div class="app-sticky-page-header__leading">
        <h1 class="app-sticky-page-header__title">{{ title }}</h1>
        <Tag v-if="showCount && countLabel" :value="countLabel" severity="contrast" rounded />
      </div>
      <div class="app-sticky-page-header__actions">
        <Button
          v-if="showReload"
          icon="pi pi-refresh"
          text
          rounded
          severity="secondary"
          :loading="reloading"
          aria-label="Actualiser"
          @click="$emit('reload')"
        />
        <slot name="actions" />
        <Button
          v-if="showCreate && createLabel && !createAsFab"
          icon="pi pi-plus"
          rounded
          :aria-label="createLabel"
          @click="$emit('create')"
        />
      </div>
    </div>

    <IconField v-if="showSearch" class="app-sticky-page-header__search">
      <InputIcon class="pi pi-search" />
      <InputText
        :model-value="searchTerm"
        :placeholder="searchPlaceholder"
        fluid
        @update:model-value="$emit('update:searchTerm', $event)"
      />
    </IconField>
  </div>
</template>

<style scoped>
.app-sticky-page-header__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.app-sticky-page-header__leading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.app-sticky-page-header__title {
  margin: 0;
  font-size: var(--app-font-title);
  font-weight: 650;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-sticky-page-header__actions {
  display: flex;
  align-items: center;
  gap: 0.15rem;
  flex-shrink: 0;
}

.app-sticky-page-header__search {
  margin-top: 0.5rem;
  width: 100%;
}
</style>
