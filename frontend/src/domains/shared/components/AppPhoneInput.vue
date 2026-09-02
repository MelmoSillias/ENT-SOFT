<template>
  <InputText
    ref="inputRef"
    :model-value="modelValue"
    :invalid="invalid"
    :disabled="disabled"
    :placeholder="placeholder"
    :fluid="fluid"
    :class="inputClass"
    inputmode="tel"
    autocomplete="tel"
    @keydown="onKeydown"
    @paste="onPaste"
    @update:model-value="onModelUpdate"
  />
</template>

<script setup>
import { nextTick, ref } from 'vue'
import InputText from 'primevue/inputtext'
import { sanitizePhoneInput } from '@/domains/shared/utils/formValidation'

const props = defineProps({
  modelValue: { type: String, default: '' },
  invalid: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  fluid: { type: Boolean, default: false },
  inputClass: { type: [String, Object, Array], default: '' },
})

const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)

function getNativeInput() {
  const root = inputRef.value?.$el ?? inputRef.value
  if (!root) return null
  if (root instanceof HTMLInputElement) return root
  return root.querySelector?.('input') ?? null
}

function syncDom(sanitized) {
  nextTick(() => {
    const el = getNativeInput()
    if (el && el.value !== sanitized) {
      el.value = sanitized
    }
  })
}

function applyValue(raw) {
  const sanitized = sanitizePhoneInput(raw)
  emit('update:modelValue', sanitized)
  // Force DOM when Vue skips re-render (same model, e.g. typing letters into empty field).
  syncDom(sanitized)
  return sanitized
}

function onModelUpdate(value) {
  applyValue(value)
}

function onKeydown(event) {
  if (event.ctrlKey || event.metaKey || event.altKey) return

  const allowedKeys = new Set([
    'Backspace',
    'Delete',
    'Tab',
    'Escape',
    'Enter',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
  ])
  if (allowedKeys.has(event.key)) return
  if (event.key.length !== 1) return

  if (/^[0-9]$/.test(event.key)) return

  const start = event.target.selectionStart ?? 0
  const current = String(props.modelValue ?? '')
  const prevChar = start > 0 ? current[start - 1] : ''

  // '+' only allowed at the start of the field
  if (event.key === '+') {
    if (start === 0) return
    event.preventDefault()
    return
  }

  // Space: not at start, not after '+', not after another space
  if (event.key === ' ') {
    if (start === 0 || prevChar === '+' || prevChar === ' ') {
      event.preventDefault()
      return
    }
    return
  }

  event.preventDefault()
}

function onPaste(event) {
  event.preventDefault()
  const text = event.clipboardData?.getData('text') ?? ''
  const el = event.target
  const selectionStart = el.selectionStart ?? 0
  const selectionEnd = el.selectionEnd ?? selectionStart
  const current = String(props.modelValue ?? '')
  applyValue(`${current.slice(0, selectionStart)}${text}${current.slice(selectionEnd)}`)
}
</script>
