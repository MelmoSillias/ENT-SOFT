<script setup>
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'

defineProps({
  title: { type: String, required: true },
  compact: { type: Boolean, default: false },
})

const scale = defineModel('scale', { type: String, required: true })

const emit = defineEmits(['prev', 'next', 'today'])

const scaleOptions = [
  { label: 'Jour', value: 'day' },
  { label: 'Semaine', value: 'week' },
]
</script>

<template>
  <div class="tl-toolbar" :class="{ 'tl-toolbar--compact': compact }">
    <div class="tl-toolbar__nav">
      <Button icon="pi pi-chevron-left" text rounded size="small" aria-label="Période précédente" @click="emit('prev')" />
      <Button icon="pi pi-chevron-right" text rounded size="small" aria-label="Période suivante" @click="emit('next')" />
      <Button label="Aujourd'hui" text size="small" @click="emit('today')" />
    </div>
    <p class="tl-toolbar__title">{{ title }}</p>
    <SelectButton
      v-model="scale"
      :options="scaleOptions"
      option-label="label"
      option-value="value"
      :allow-empty="false"
      size="small"
    />
  </div>
</template>

<style scoped>
.tl-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding-bottom: 0.75rem;
}

.tl-toolbar__nav {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.tl-toolbar__title {
  margin: 0;
  font-weight: 600;
  font-size: 1rem;
  text-transform: capitalize;
  flex: 1;
  text-align: center;
  min-width: 10rem;
}

.tl-toolbar--compact .tl-toolbar__title {
  font-size: 0.875rem;
  min-width: 0;
  order: 3;
  flex-basis: 100%;
  text-align: left;
}
</style>
