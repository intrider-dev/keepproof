<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BrandMark from '../components/BrandMark.vue'
import { appStore } from '../stores/app'

const router = useRouter()

onMounted(async () => {
  appStore.startDemo()
  await router.replace('/app/dashboard')
})
</script>

<template>
  <main id="main-content" class="demo-loader" tabindex="-1" aria-live="polite">
    <BrandMark />
    <div class="spinner spinner--large" aria-hidden="true"></div>
    <h1>Preparing your local demo</h1>
    <p>Loading synthetic receipts. No data is leaving this browser.</p>
  </main>
</template>
