<script setup lang="ts">
withDefaults(
  defineProps<{
    tone?: 'green' | 'amber' | 'neutral' | 'red' | 'blue'
  }>(),
  { tone: 'neutral' },
)
</script>

<template>
  <span class="status-pill" :class="`status-pill--${tone}`"><slot /></span>
</template>
