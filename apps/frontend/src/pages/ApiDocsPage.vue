<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-es-bundle.js'
import 'swagger-ui-dist/swagger-ui.css'
import BrandMark from '../components/BrandMark.vue'

onMounted(() => {
  SwaggerUIBundle({
    dom_id: '#swagger-ui',
    url: '/docs/openapi.yaml',
    deepLinking: true,
    displayRequestDuration: true,
    persistAuthorization: false,
    presets: [SwaggerUIBundle.presets.apis],
    requestInterceptor: (request) => {
      request.credentials = 'include'
      return request
    },
  })
})
</script>

<template>
  <div class="api-docs-page">
    <header class="api-docs-header">
      <RouterLink class="brand" to="/" aria-label="KeepProof home">
        <BrandMark />
        <span>KeepProof</span>
      </RouterLink>
      <div>
        <p class="eyebrow">Versioned REST contract</p>
        <h1>API documentation</h1>
      </div>
      <a class="button button--secondary" href="/docs/openapi.yaml">Open YAML</a>
    </header>
    <div class="api-docs-notice">
      Try-it-out requests stay on this origin and use the current Sanctum session. Initialize the
      CSRF cookie before unsafe calls. Never paste production credentials into local documentation.
    </div>
    <main id="main-content" tabindex="-1">
      <div id="swagger-ui" aria-label="KeepProof OpenAPI operations"></div>
    </main>
  </div>
</template>

<style>
.api-docs-page {
  min-height: 100vh;
  background: var(--color-canvas);
}

.api-docs-header {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 2rem;
  padding: 1.25rem clamp(1.25rem, 4vw, 4rem);
  border-bottom: 1px solid var(--color-border);
}

.api-docs-header h1 {
  margin: 0.2rem 0 0;
  font-size: clamp(1.35rem, 3vw, 2rem);
}

.api-docs-header .eyebrow {
  margin: 0;
}

.api-docs-notice {
  max-width: 76rem;
  margin: 1.5rem auto 0;
  padding: 1rem 1.25rem;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  line-height: 1.55;
}

.api-docs-page #main-content {
  outline: none;
}

.api-docs-page .swagger-ui {
  color: var(--color-text);
  font-family: var(--font-sans);
}

.api-docs-page .swagger-ui .topbar,
.api-docs-page .swagger-ui .information-container .info .title small {
  display: none;
}

.api-docs-page .swagger-ui .wrapper {
  max-width: 76rem;
  padding-inline: 0;
}

.api-docs-page .swagger-ui .info {
  margin-block: 2.5rem 2rem;
}

.api-docs-page .swagger-ui .opblock,
.api-docs-page .swagger-ui .scheme-container {
  border-radius: var(--radius-md);
  box-shadow: none;
}

@media (max-width: 700px) {
  .api-docs-header {
    grid-template-columns: 1fr auto;
  }

  .api-docs-header > div {
    grid-column: 1 / -1;
    grid-row: 2;
  }

  .api-docs-notice,
  .api-docs-page #swagger-ui {
    margin-inline: 1rem;
  }
}
</style>
