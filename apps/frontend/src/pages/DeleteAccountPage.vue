<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import { demoStore } from '../demo/store'
import { deletionPhraseIsValid } from '../validation'

const router = useRouter()
const phrase = ref('')
const password = ref('')
const understood = ref(false)
const busy = ref(false)
const attempted = ref(false)

const canDelete = computed(
  () => deletionPhraseIsValid(phrase.value) && password.value.length > 0 && understood.value,
)

const submit = async (): Promise<void> => {
  attempted.value = true
  if (busy.value || !canDelete.value) return
  busy.value = true
  try {
    demoStore.deleteDemoAccount()
    await router.push({ path: '/', query: { deleted: '1' } })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-stack delete-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <RouterLink to="/app/privacy">Privacy & data</RouterLink><span aria-hidden="true">/</span
      ><span aria-current="page">Delete account</span>
    </nav>
    <PageHeader
      eyebrow="Permanent action"
      title="Delete your KeepProof account"
      description="This clears the local demo immediately. Connected mode first removes private files, then relational account data."
    />

    <div class="delete-layout">
      <section class="panel delete-form-card">
        <div class="danger-heading">
          <span><AppIcon name="shield" :size="24" /></span>
          <div>
            <h2>This cannot be undone</h2>
            <p>Export anything you want to keep before continuing.</p>
          </div>
        </div>
        <ul class="deletion-list">
          <li>
            <AppIcon name="check" :size="17" /> Profile, preferences, and in-app notifications
          </li>
          <li><AppIcon name="check" :size="17" /> Sole-owned household purchases and deadlines</li>
          <li><AppIcon name="check" :size="17" /> Private receipt files and generated exports</li>
        </ul>
        <form class="form-stack" @submit.prevent="submit">
          <div class="field">
            <label for="delete-password">Confirm your password</label>
            <input
              id="delete-password"
              v-model="password"
              class="input"
              type="password"
              autocomplete="current-password"
              :aria-invalid="attempted && !password"
            />
            <p v-if="attempted && !password" class="field-error">Enter your password.</p>
          </div>
          <div class="field">
            <label for="delete-phrase">Type <strong>DELETE MY ACCOUNT</strong></label>
            <input
              id="delete-phrase"
              v-model="phrase"
              class="input"
              autocomplete="off"
              :aria-invalid="attempted && !deletionPhraseIsValid(phrase)"
            />
            <p v-if="attempted && !deletionPhraseIsValid(phrase)" class="field-error">
              Type the confirmation phrase exactly.
            </p>
          </div>
          <label class="danger-check">
            <input v-model="understood" type="checkbox" />
            <span>I understand that this removes the synthetic demo records and signs me out.</span>
          </label>
          <button
            class="button button--danger button--full"
            type="submit"
            :disabled="busy || !canDelete"
          >
            <span v-if="busy" class="spinner" aria-hidden="true"></span>
            {{ busy ? 'Deleting demo data…' : 'Permanently delete account' }}
          </button>
        </form>
      </section>

      <aside class="page-stack page-stack--compact">
        <section class="panel">
          <p class="eyebrow">Before deleting</p>
          <h2>Take your data with you</h2>
          <p>Return to privacy controls to download a synthetic JSON export first.</p>
          <RouterLink class="button button--secondary button--full" to="/app/privacy"
            ><AppIcon name="package" :size="18" /> Export instead</RouterLink
          >
        </section>
        <div class="legal-note">
          <AppIcon name="users" />
          <p>
            <strong>Shared household safeguard.</strong> In connected mode, owners must transfer
            ownership before deleting an account that has other members.
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>
