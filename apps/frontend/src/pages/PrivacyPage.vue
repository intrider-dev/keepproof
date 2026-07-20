<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { appStore } from '../stores/app'
import { ApiProblem, apiDownload, apiRequest } from '../lib/api'
import { downloadBlob, downloadTextFile } from '../lib/download'

const exporting = ref(false)
const exportReady = ref(false)
const exportPassword = ref('')
const exportError = ref('')
const removeAfterExtraction = ref(false)

const exportAccount = async (): Promise<void> => {
  if (exporting.value) return
  exporting.value = true
  exportError.value = ''
  try {
    if (!appStore.state.demoMode) {
      if (!exportPassword.value) {
        exportError.value = 'Enter your password to authorize this export.'
        return
      }
      await apiRequest('/auth/confirm-password', {
        method: 'POST',
        body: JSON.stringify({ password: exportPassword.value }),
      })
      const archive = await apiDownload('/account/export')
      downloadBlob('keepproof-account-export.zip', archive)
      exportPassword.value = ''
      exportReady.value = true
      appStore.showToast('Your private account export was downloaded.')
      return
    }

    const data = {
      generated_at: new Date().toISOString(),
      demo_notice: 'Synthetic local demo data',
      account: appStore.state.user,
      memberships: appStore.state.members,
      purchases: appStore.state.purchases,
      notification_preferences: appStore.state.preferences,
    }
    downloadTextFile(
      'keepproof-demo-export.json',
      JSON.stringify(data, null, 2),
      'application/json',
    )
    exportReady.value = true
    appStore.showToast('Your synthetic account export was downloaded.')
  } catch (caught) {
    exportError.value =
      caught instanceof ApiProblem
        ? caught.problem.detail
        : 'The account export could not be prepared.'
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Privacy & portability"
      title="Your data stays yours"
      description="See where receipt data goes, export a portable copy, or remove your account."
    />

    <section class="privacy-dashboard">
      <article>
        <span class="feature-icon feature-icon--large"><AppIcon name="shield" :size="27" /></span>
        <div>
          <p class="eyebrow">Processing</p>
          <h2>Local-first</h2>
          <p>
            Deterministic extraction and local OCR do not send receipt text to an external AI
            provider.
          </p>
        </div>
        <StatusPill tone="green">External AI off</StatusPill>
      </article>
      <article>
        <span class="feature-icon feature-icon--large"><AppIcon name="file" :size="27" /></span>
        <div>
          <p class="eyebrow">Private storage</p>
          <h2>
            {{ appStore.state.purchases.filter((purchase) => purchase.documentId).length }} receipt
            files
          </h2>
          <p>Connected downloads use authorized links that expire after a short period.</p>
        </div>
        <StatusPill tone="neutral">Private</StatusPill>
      </article>
      <article>
        <span class="feature-icon feature-icon--large"><AppIcon name="package" :size="27" /></span>
        <div>
          <p class="eyebrow">Portability</p>
          <h2>Open formats</h2>
          <p>Account data exports as JSON; calendar deadlines export as ICS.</p>
        </div>
        <StatusPill tone="blue">No lock-in</StatusPill>
      </article>
    </section>

    <div class="privacy-grid">
      <section class="panel settings-section">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Account export</p>
            <h2>Download a portable copy</h2>
          </div>
          <AppIcon name="package" />
        </div>
        <p>
          Your export includes profile settings, memberships, confirmed purchases, deadlines, and
          the receipt files you chose to retain.
        </p>
        <label v-if="!appStore.state.demoMode" class="field">
          <span>Confirm your password</span>
          <input
            v-model="exportPassword"
            class="input"
            type="password"
            autocomplete="current-password"
            :aria-invalid="Boolean(exportError && !exportPassword)"
          />
        </label>
        <div v-if="exportReady" class="callout callout--green" role="status">
          <AppIcon name="check" />
          <p>
            <strong>Export downloaded</strong>
            {{
              appStore.state.demoMode
                ? 'The browser generated a synthetic JSON fixture export.'
                : 'The backend packaged authorized account data and retained documents.'
            }}
          </p>
        </div>
        <p v-if="exportError" class="form-alert" role="alert">{{ exportError }}</p>
        <button
          class="button button--primary"
          type="button"
          :disabled="exporting"
          @click="exportAccount"
        >
          <span v-if="exporting" class="spinner" aria-hidden="true"></span>
          <AppIcon v-else name="package" :size="18" />
          {{ exporting ? 'Preparing export…' : 'Export my data' }}
        </button>
        <p class="field-hint">
          Connected mode requires a recent password confirmation and writes an audit event.
        </p>
      </section>

      <section class="panel settings-section">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Source retention</p>
            <h2>Keep only what you need</h2>
          </div>
          <AppIcon name="receipt" />
        </div>
        <label class="setting-row">
          <span
            ><strong>Remove source after extraction</strong
            ><small
              >Keep confirmed purchase data but delete the original receipt after review.</small
            ></span
          >
          <input v-model="removeAfterExtraction" class="switch" type="checkbox" />
        </label>
        <p class="field-hint">
          This demo preference is illustrative and does not delete fixture assets.
        </p>
      </section>
    </div>

    <section class="panel data-map">
      <div class="panel__heading">
        <div>
          <p class="eyebrow">Transparent flow</p>
          <h2>Where your receipt data goes</h2>
        </div>
      </div>
      <ol>
        <li>
          <span>1</span>
          <div>
            <strong>Private upload</strong>
            <p>
              The backend validates content and removes image metadata before private object
              storage.
            </p>
          </div>
        </li>
        <li>
          <span>2</span>
          <div>
            <strong>Local extraction</strong>
            <p>OCR output is stored separately from proposed normalized fields.</p>
          </div>
        </li>
        <li>
          <span>3</span>
          <div>
            <strong>Your confirmation</strong>
            <p>Only values you confirm can calculate active deadlines and reminders.</p>
          </div>
        </li>
        <li>
          <span>4</span>
          <div>
            <strong>Portable output</strong>
            <p>Use JSON, ICS, and claim packages without a vendor-specific format.</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="danger-zone">
      <div>
        <p class="eyebrow">Danger zone</p>
        <h2>Delete this account</h2>
        <p>
          Connected deletion removes private files before the final account record and can be
          retried safely after a partial failure.
        </p>
      </div>
      <RouterLink class="button button--danger-outline" to="/app/privacy/delete"
        >Review account deletion</RouterLink
      >
    </section>
  </div>
</template>
