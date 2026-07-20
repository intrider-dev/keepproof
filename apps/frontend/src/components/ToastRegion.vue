<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import { appStore } from '../stores/app'
</script>

<template>
  <div class="toast-region" aria-live="polite" aria-atomic="true">
    <div v-if="appStore.toast.value" class="toast" :class="`toast--${appStore.toast.value.tone}`">
      <AppIcon name="check" :size="18" />
      <span>{{ appStore.toast.value.message }}</span>
      <button
        class="icon-button"
        type="button"
        aria-label="Dismiss notification"
        @click="appStore.toast.value = null"
      >
        ×
      </button>
    </div>
  </div>
</template>
