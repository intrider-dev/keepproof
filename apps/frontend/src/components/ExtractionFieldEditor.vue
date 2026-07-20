<script setup lang="ts">
import { computed } from 'vue'
import type { ExtractionField } from '../types'
import ConfidenceBadge from './ConfidenceBadge.vue'

const props = defineProps<{
  field: ExtractionField
  modelValue: string
  error?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const inputId = computed(() => `extraction-${props.field.key}`)
const describedBy = computed(() => {
  const ids = [`${inputId.value}-meta`]
  if (props.field.errors.length || props.error) ids.push(`${inputId.value}-errors`)
  return ids.join(' ')
})
</script>

<template>
  <div class="extraction-field" :class="{ 'extraction-field--warning': field.errors.length }">
    <div class="extraction-field__heading">
      <label :for="inputId">{{ field.label }}</label>
      <ConfidenceBadge :confidence="field.confidence" :source="field.source" />
    </div>
    <input
      :id="inputId"
      class="input"
      :type="field.type ?? 'text'"
      :inputmode="field.inputMode"
      :value="modelValue"
      :aria-invalid="Boolean(error)"
      :aria-describedby="describedBy"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <div :id="`${inputId}-meta`" class="field-meta">
      <span>{{ field.source }}</span>
      <span v-if="field.normalizedValue !== field.value">
        Normalized: {{ field.normalizedValue }}
      </span>
    </div>
    <ul
      v-if="field.errors.length || error"
      :id="`${inputId}-errors`"
      class="field-messages"
      :aria-live="error ? 'polite' : 'off'"
    >
      <li v-if="error" class="field-error">{{ error }}</li>
      <li v-for="message in field.errors" :key="message">{{ message }}</li>
    </ul>
  </div>
</template>
