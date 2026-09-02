<script setup>
import { computed, ref, watch } from 'vue'
import { countryFlagEmoji, countryFlagUrl } from '@/domains/shared/utils/countryFlag'

const props = defineProps({
  code: { type: String, default: '' },
  size: { type: Number, default: 24 },
  showEmoji: { type: Boolean, default: true },
})

const imageFailed = ref(false)

const emoji = computed(() => countryFlagEmoji(props.code))
const resolvedSrc = computed(() => countryFlagUrl(props.code, props.size))

function onImageError() {
  imageFailed.value = true
}

watch(
  () => [props.code, props.size],
  () => {
    imageFailed.value = false
  },
)
</script>

<template>
  <span class="country-flag" :style="{ width: `${size}px`, minWidth: `${size}px` }">
    <img
      v-if="!imageFailed && resolvedSrc"
      :src="resolvedSrc"
      :alt="code"
      :width="size"
      :height="Math.round(size * 0.75)"
      class="country-flag__img"
      referrerpolicy="no-referrer"
      loading="lazy"
      @error="onImageError"
    />
    <span v-else-if="showEmoji" class="country-flag__emoji" :style="{ fontSize: `${Math.round(size * 0.85)}px` }">
      {{ emoji }}
    </span>
  </span>
</template>

<style scoped>
.country-flag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.country-flag__img {
  border-radius: 3px;
  object-fit: cover;
  display: block;
}

.country-flag__emoji {
  line-height: 1;
}
</style>
