<script setup lang="ts">
import { computed } from 'vue'
import { confidencePercent } from '../lib/format'

const props = defineProps<{
  confidence: number
  source?: string
}>()

const tone = computed(() => {
  if (props.confidence >= 0.9) return 'high'
  if (props.confidence >= 0.8) return 'medium'
  return 'low'
})
</script>

<template>
  <span
    class="confidence"
    :class="`confidence--${tone}`"
    :aria-label="`${confidencePercent(confidence)} confidence${source ? `, from ${source}` : ''}`"
  >
    <span class="confidence__dot"></span>
    {{ confidencePercent(confidence) }}
  </span>
</template>
