<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

withDefaults(
  defineProps<{
    icon?: IconName
    title: string
    description: string
  }>(),
  { icon: 'receipt' },
)
</script>

<template>
  <div class="empty-state">
    <span class="empty-state__icon"><AppIcon :name="icon" :size="24" /></span>
    <h2>{{ title }}</h2>
    <p>{{ description }}</p>
    <div v-if="$slots.default" class="empty-state__actions"><slot /></div>
  </div>
</template>
