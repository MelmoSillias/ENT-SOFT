<script setup>
import InputNumber from 'primevue/inputnumber'

const model = defineModel({ type: Number, default: null })

defineProps({
  min: { type: Number, default: undefined },
  max: { type: Number, default: undefined },
  minFractionDigits: { type: Number, default: undefined },
  maxFractionDigits: { type: Number, default: undefined },
  readonly: { type: Boolean, default: false },
  fluid: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
})

function onInput(event) {
  model.value = event.value
}
</script>

<template>
  <InputNumber
    v-model="model"
    :min="min"
    :max="max"
    :min-fraction-digits="minFractionDigits"
    :max-fraction-digits="maxFractionDigits"
    :readonly="readonly"
    :fluid="fluid"
    :invalid="invalid"
    @input="onInput"
  />
</template>
