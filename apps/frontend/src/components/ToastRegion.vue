<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import { demoStore } from '../demo/store'
</script>

<template>
  <div class="toast-region" aria-live="polite" aria-atomic="true">
    <div v-if="demoStore.toast.value" class="toast" :class="`toast--${demoStore.toast.value.tone}`">
      <AppIcon name="check" :size="18" />
      <span>{{ demoStore.toast.value.message }}</span>
      <button
        class="icon-button"
        type="button"
        aria-label="Dismiss notification"
        @click="demoStore.toast.value = null"
      >
        ×
      </button>
    </div>
  </div>
</template>
