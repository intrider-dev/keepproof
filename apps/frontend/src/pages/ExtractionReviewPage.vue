<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import EmptyState from '../components/EmptyState.vue'
import ExtractionFieldEditor from '../components/ExtractionFieldEditor.vue'
import PageHeader from '../components/PageHeader.vue'
import { appStore } from '../stores/app'
import { ApiProblem } from '../lib/api'
import { validateExtraction, type ValidationErrors } from '../validation'

const route = useRoute()
const router = useRouter()
const document = computed(() => appStore.documentById(String(route.params.documentId)))
const values = reactive<Record<string, string>>({})
const errors = ref<ValidationErrors>({})
const acknowledged = ref(false)
const acknowledgementError = ref('')
const busy = ref(false)
const requestError = ref('')

for (const field of document.value?.fields ?? []) values[field.key] = field.value

const confidenceAverage = computed(() => {
  const fields = document.value?.fields ?? []
  if (!fields.length) return 0
  return Math.round(
    (fields.reduce((sum, field) => sum + field.confidence, 0) / fields.length) * 100,
  )
})

const warningCount = computed(
  () => document.value?.fields.filter((field) => field.errors.length).length ?? 0,
)

const submit = async (): Promise<void> => {
  if (busy.value || !document.value) return
  errors.value = validateExtraction(values)
  acknowledgementError.value = acknowledged.value ? '' : 'Confirm that you reviewed the values.'
  if (Object.keys(errors.value).length || acknowledgementError.value) {
    await nextTick()
    globalThis.document
      .querySelector<HTMLElement>('[aria-invalid="true"], #review-acknowledgement')
      ?.focus()
    return
  }

  busy.value = true
  requestError.value = ''
  try {
    const purchaseId = await appStore.confirmExtraction(document.value.id, values)
    await router.push(`/app/purchases/${purchaseId}`)
  } catch (error) {
    requestError.value =
      error instanceof ApiProblem
        ? error.problem.detail
        : error instanceof Error
          ? error.message
          : 'Purchase confirmation failed.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div v-if="document" class="page-stack review-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <RouterLink to="/app/purchases">Purchases</RouterLink><span aria-hidden="true">/</span
      ><span aria-current="page">Review extraction</span>
    </nav>
    <PageHeader
      eyebrow="Human review required"
      title="Check what KeepProof found"
      description="Compare every proposed value with the receipt. Your edits become the source of truth only when you confirm."
    />

    <div class="review-layout">
      <aside class="receipt-inspector">
        <div class="receipt-inspector__top">
          <div>
            <p class="eyebrow">Source document</p>
            <h2>{{ document.filename }}</h2>
          </div>
          <span class="local-badge"><AppIcon name="shield" :size="15" /> Local</span>
        </div>
        <div class="synthetic-receipt">
          <div class="synthetic-receipt__merchant">BRIGHTLINE HOME</div>
          <p>18 JUL 2026 · BH-73104</p>
          <hr />
          <div><span>LINEN FLOOR LAMP</span><span>$139.50</span></div>
          <div><span>SUBTOTAL</span><span>$129.17</span></div>
          <div><span>TAX</span><span>$10.33</span></div>
          <hr />
          <div class="synthetic-receipt__total"><span>TOTAL USD</span><span>$139.50</span></div>
          <p class="synthetic-receipt__policy">
            RETURNS WITHIN 30 DAYS<br />12 MONTH LIMITED WARRANTY
          </p>
        </div>
        <div class="extraction-summary">
          <div>
            <strong>{{ confidenceAverage }}%</strong><span>Average confidence</span>
          </div>
          <div>
            <strong>{{ warningCount }}</strong
            ><span>Fields to double-check</span>
          </div>
        </div>
        <p class="panel-note">
          <AppIcon name="shield" :size="16" /> OCR text is stored separately from confirmed purchase
          data.
        </p>
      </aside>

      <form class="review-form" novalidate @submit.prevent="submit">
        <div class="review-form__intro">
          <div>
            <p class="eyebrow">Extracted fields</p>
            <h2>Purchase details</h2>
          </div>
          <span>{{ document.fields.length }} fields</span>
        </div>
        <div class="confidence-legend" aria-label="Confidence legend">
          <span><i class="legend-dot legend-dot--high"></i>90–100% high</span>
          <span><i class="legend-dot legend-dot--medium"></i>80–89% check</span>
          <span><i class="legend-dot legend-dot--low"></i>Below 80% review closely</span>
        </div>

        <ExtractionFieldEditor
          v-for="field in document.fields"
          :key="field.key"
          v-model="values[field.key]"
          :field="field"
          :error="errors[field.key]"
        />

        <div class="review-confirmation">
          <label>
            <input
              id="review-acknowledgement"
              v-model="acknowledged"
              type="checkbox"
              :aria-invalid="Boolean(acknowledgementError)"
              :aria-describedby="acknowledgementError ? 'acknowledgement-error' : undefined"
            />
            <span
              ><strong>I checked these values against the receipt.</strong
              ><small
                >KeepProof will calculate and activate deadlines from the confirmed details.</small
              ></span
            >
          </label>
          <p v-if="acknowledgementError" id="acknowledgement-error" class="field-error">
            {{ acknowledgementError }}
          </p>
        </div>

        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>

        <div class="sticky-form-actions">
          <RouterLink class="button button--quiet" to="/app/purchases">Save for later</RouterLink>
          <button class="button button--primary" type="submit" :disabled="busy">
            <span v-if="busy" class="spinner" aria-hidden="true"></span>
            {{ busy ? 'Activating deadlines…' : 'Confirm purchase details' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <EmptyState
    v-else
    icon="file"
    title="Extraction not found"
    description="This review may already be complete or belong to another household."
  >
    <RouterLink class="button button--secondary" to="/app/purchases">Back to purchases</RouterLink>
  </EmptyState>
</template>
