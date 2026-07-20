<script setup lang="ts">
import BrandMark from '../components/BrandMark.vue'
</script>

<template>
  <main id="main-content" class="not-found" tabindex="-1">
    <BrandMark />
    <p class="eyebrow">404 · Nothing stored here</p>
    <h1>We could not find that page.</h1>
    <p>The link may be old, or the resource may belong to another private workspace.</p>
    <RouterLink class="button button--primary" to="/">Return to KeepProof</RouterLink>
  </main>
</template>
