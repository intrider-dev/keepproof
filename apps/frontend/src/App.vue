<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterView } from 'vue-router'
import ToastRegion from './components/ToastRegion.vue'

const online = ref(navigator.onLine)

const updateConnection = () => {
  online.value = navigator.onLine
}

onMounted(() => {
  window.addEventListener('online', updateConnection)
  window.addEventListener('offline', updateConnection)
})

onBeforeUnmount(() => {
  window.removeEventListener('online', updateConnection)
  window.removeEventListener('offline', updateConnection)
})
</script>

<template>
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div v-if="!online" class="offline-banner" role="status">
    You are offline. Your local demo remains available; connected actions will wait.
  </div>
  <RouterView />
  <ToastRegion />
</template>
