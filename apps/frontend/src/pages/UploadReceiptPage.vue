<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import { appStore } from '../stores/app'

const router = useRouter()
const step = ref(1)
const selectedName = ref('')
const selectedSize = ref('')
const selectedFile = ref<File>()
const fileError = ref('')
const allowExternalAi = ref(false)
const busy = ref(false)

const chooseFile = (event: Event): void => {
  const file = (event.target as HTMLInputElement).files?.[0]
  fileError.value = ''
  if (!file) return
  const allowed = ['image/jpeg', 'image/png', 'application/pdf']
  if (!allowed.includes(file.type)) {
    fileError.value = 'Choose a JPG, PNG, or PDF file.'
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    fileError.value = 'Choose a file smaller than 10 MB.'
    return
  }
  selectedFile.value = file
  selectedName.value = file.name
  selectedSize.value = `${(file.size / 1024 / 1024).toFixed(1)} MB`
}

const useSynthetic = async (): Promise<void> => {
  fileError.value = ''
  try {
    if (!appStore.state.demoMode) {
      const response = await fetch('/demo/northstar-receipt.png')
      if (!response.ok) throw new Error('Synthetic receipt unavailable.')
      const blob = await response.blob()
      selectedFile.value = new File([blob], 'northstar-receipt.png', { type: 'image/png' })
      selectedSize.value = `${(blob.size / 1024).toFixed(0)} KB`
    } else {
      selectedSize.value = '9 KB'
    }
    selectedName.value = 'northstar-receipt.png'
    step.value = 2
  } catch {
    fileError.value = 'The synthetic receipt could not be loaded. Choose a local file instead.'
  }
}

const continueFromFile = (): void => {
  if (!selectedName.value) {
    fileError.value = 'Choose a receipt or use the synthetic demo receipt.'
    return
  }
  step.value = 2
}

const processReceipt = async (): Promise<void> => {
  if (busy.value) return
  busy.value = true
  fileError.value = ''
  try {
    const documentId = await appStore.uploadReceipt(selectedFile.value)
    await router.push(`/app/review/${documentId}`)
    appStore.showToast('Local extraction complete. Review the proposed values.', 'info')
  } catch (error) {
    fileError.value = error instanceof Error ? error.message : 'Receipt processing failed.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-stack upload-page">
    <PageHeader
      eyebrow="New purchase"
      title="Add proof of purchase"
      description="Upload a receipt now, then review every extracted detail before any deadline becomes active."
    />

    <nav class="entry-methods" aria-label="Choose how to add a purchase">
      <RouterLink class="entry-methods__active" to="/app/purchases/new" aria-current="page">
        <AppIcon name="upload" :size="18" /> Upload a receipt
      </RouterLink>
      <RouterLink to="/app/purchases/new/manual">
        <AppIcon name="edit" :size="18" /> Enter manually
      </RouterLink>
    </nav>

    <ol class="stepper" aria-label="Upload progress">
      <li :class="{ 'stepper__item--active': step === 1, 'stepper__item--complete': step > 1 }">
        <span>{{ step > 1 ? '✓' : '1' }}</span>
        <p><strong>Receipt</strong><small>Choose a file</small></p>
      </li>
      <li :class="{ 'stepper__item--active': step === 2, 'stepper__item--complete': step > 2 }">
        <span>{{ step > 2 ? '✓' : '2' }}</span>
        <p><strong>Privacy</strong><small>Choose processing</small></p>
      </li>
      <li :class="{ 'stepper__item--active': step === 3 }">
        <span>3</span>
        <p><strong>Process</strong><small>Create review</small></p>
      </li>
    </ol>

    <section v-if="step === 1" class="wizard-card" aria-labelledby="upload-step-title">
      <div class="wizard-card__heading">
        <span class="feature-icon feature-icon--large"><AppIcon name="upload" :size="27" /></span>
        <div>
          <p class="eyebrow">Step 1 of 3</p>
          <h2 id="upload-step-title">Choose your receipt</h2>
          <p>KeepProof accepts clear JPG, PNG, and PDF files up to 10 MB.</p>
        </div>
      </div>

      <label class="drop-zone" :class="{ 'drop-zone--selected': selectedName }">
        <input
          class="sr-only"
          type="file"
          accept="image/jpeg,image/png,application/pdf"
          :aria-describedby="fileError ? 'file-error upload-help' : 'upload-help'"
          @change="chooseFile"
        />
        <span class="drop-zone__icon"
          ><AppIcon :name="selectedName ? 'check' : 'file'" :size="25"
        /></span>
        <span v-if="selectedName">
          <strong>{{ selectedName }}</strong>
          <small>{{ selectedSize }} · Ready to continue</small>
        </span>
        <span v-else>
          <strong>Choose a file</strong>
          <small>or drop it here</small>
        </span>
      </label>
      <p id="upload-help" class="privacy-hint">
        <AppIcon name="shield" :size="17" /> Image metadata is removed before private storage.
      </p>
      <p v-if="fileError" id="file-error" class="form-alert" role="alert">{{ fileError }}</p>

      <div class="demo-receipt-option">
        <div>
          <span class="feature-icon"><AppIcon name="spark" /></span>
          <span
            ><strong>Just exploring?</strong
            ><small>Use a safe synthetic receipt made for this demo.</small></span
          >
        </div>
        <button class="button button--secondary" type="button" @click="useSynthetic">
          Use synthetic receipt
        </button>
      </div>

      <div class="wizard-actions">
        <RouterLink class="button button--quiet" to="/app/purchases">Cancel</RouterLink>
        <button class="button button--primary" type="button" @click="continueFromFile">
          Continue <AppIcon name="arrow" :size="18" />
        </button>
      </div>
    </section>

    <section v-else-if="step === 2" class="wizard-card" aria-labelledby="privacy-step-title">
      <div class="wizard-card__heading">
        <span class="feature-icon feature-icon--large"><AppIcon name="shield" :size="27" /></span>
        <div>
          <p class="eyebrow">Step 2 of 3</p>
          <h2 id="privacy-step-title">Choose how this receipt is processed</h2>
          <p>Local processing is the default and works without an external AI account.</p>
        </div>
      </div>

      <div class="processing-options">
        <label class="choice-card choice-card--selected">
          <input type="radio" name="processing" checked />
          <span class="choice-card__check"><AppIcon name="check" :size="17" /></span>
          <span
            ><strong>Process locally</strong
            ><small
              >Deterministic demo extraction or local OCR. Receipt text stays in this
              environment.</small
            ></span
          >
          <span class="recommended">Recommended</span>
        </label>
        <label class="choice-card choice-card--disabled">
          <input v-model="allowExternalAi" type="checkbox" disabled />
          <span class="choice-card__check"></span>
          <span
            ><strong>Allow external AI for this document</strong
            ><small
              >Unavailable in the local demo. Production use also requires an enabled provider and
              redaction.</small
            ></span
          >
          <span class="status-label">Off</span>
        </label>
      </div>

      <div class="data-route">
        <div>
          <AppIcon name="file" /><span
            ><strong>Your receipt</strong><small>{{ selectedName }}</small></span
          >
        </div>
        <AppIcon name="arrow" />
        <div>
          <AppIcon name="shield" /><span
            ><strong>Local worker</strong><small>No external request</small></span
          >
        </div>
        <AppIcon name="arrow" />
        <div>
          <AppIcon name="check" /><span
            ><strong>Your review</strong><small>Required to activate</small></span
          >
        </div>
      </div>

      <div class="wizard-actions">
        <button class="button button--quiet" type="button" @click="step = 1">Back</button>
        <button class="button button--primary" type="button" @click="step = 3">
          Continue with local processing <AppIcon name="arrow" :size="18" />
        </button>
      </div>
    </section>

    <section v-else class="wizard-card" aria-labelledby="process-step-title">
      <div class="wizard-card__heading">
        <span class="feature-icon feature-icon--large"><AppIcon name="spark" :size="27" /></span>
        <div>
          <p class="eyebrow">Step 3 of 3</p>
          <h2 id="process-step-title">Ready for local extraction</h2>
          <p>
            KeepProof will create field-level results for you to review. No deadline is active yet.
          </p>
        </div>
      </div>
      <dl class="review-summary">
        <div>
          <dt>Receipt</dt>
          <dd>{{ selectedName }}</dd>
        </div>
        <div>
          <dt>Processing</dt>
          <dd>{{ appStore.state.demoMode ? 'Local fixture extractor' : 'Local OCR worker' }}</dd>
        </div>
        <div>
          <dt>External AI</dt>
          <dd>Off</dd>
        </div>
        <div>
          <dt>Next step</dt>
          <dd>Human review</dd>
        </div>
      </dl>
      <div class="callout callout--green">
        <AppIcon name="shield" />
        <p v-if="appStore.state.demoMode">
          <strong>Demo boundary</strong>The selected browser file is not uploaded or read. This
          explicit fixture demo uses deterministic synthetic extraction data.
        </p>
        <p v-else>
          <strong>Private processing</strong>The selected file is uploaded to private object
          storage, then processed by the local queue worker. External AI stays off.
        </p>
      </div>
      <p v-if="fileError" class="form-alert" role="alert">{{ fileError }}</p>
      <div class="wizard-actions">
        <button class="button button--quiet" type="button" @click="step = 2">Back</button>
        <button
          class="button button--primary"
          type="button"
          :disabled="busy"
          @click="processReceipt"
        >
          <span v-if="busy" class="spinner" aria-hidden="true"></span>
          {{ busy ? 'Processing locally…' : 'Process receipt' }}
        </button>
      </div>
    </section>
  </div>
</template>
