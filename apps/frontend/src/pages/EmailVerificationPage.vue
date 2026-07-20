<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import type { UserResponse } from '../api/generated'
import AppIcon from '../components/AppIcon.vue'
import BrandMark from '../components/BrandMark.vue'
import { appStore } from '../stores/app'
import { ApiProblem, apiRequest } from '../lib/api'

const route = useRoute()
const router = useRouter()
const busy = ref(false)
const message = ref('')
const error = ref('')
const localTools = import.meta.env.VITE_DEMO_MODE !== 'false'

const refreshVerification = async (): Promise<void> => {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    const response = await apiRequest<UserResponse>('/me')
    const verified = await appStore.activateRemoteSession(response)
    if (verified) {
      appStore.showToast('Email verified. Your household is ready.')
      await router.replace('/app/dashboard')
      return
    }
    message.value = 'The address is not verified yet. Open the newest email and try again.'
  } catch (caught) {
    error.value =
      caught instanceof ApiProblem
        ? caught.problem.detail
        : 'Verification status could not be refreshed.'
  } finally {
    busy.value = false
  }
}

const resend = async (): Promise<void> => {
  if (busy.value) return
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    await apiRequest<void>('/email/verification-notification', { method: 'POST' })
    message.value = 'A new verification email was sent.'
  } catch (caught) {
    error.value =
      caught instanceof ApiProblem ? caught.problem.detail : 'The email could not be resent.'
  } finally {
    busy.value = false
  }
}

const signOut = async (): Promise<void> => {
  await appStore.signOut()
  await router.push('/login')
}

onMounted(async () => {
  if (route.query.verified === '1') await refreshVerification()
})
</script>

<template>
  <main id="main-content" class="auth-page" tabindex="-1">
    <section class="auth-panel" aria-labelledby="verification-title">
      <RouterLink class="brand" to="/"><BrandMark /><span>KeepProof</span></RouterLink>
      <div class="auth-panel__heading">
        <p class="eyebrow">One security step</p>
        <h1 id="verification-title">Verify your email</h1>
        <p>
          We sent a signed link to <strong>{{ appStore.state.user.email }}</strong
          >. Verification is required before household data can be opened.
        </p>
      </div>

      <div class="callout callout--green">
        <AppIcon name="shield" />
        <p>
          <strong>Your session is already protected.</strong>The signed link expires and can verify
          only this account.
        </p>
      </div>

      <p v-if="message" class="form-success" role="status">{{ message }}</p>
      <p v-if="error" class="form-alert" role="alert">{{ error }}</p>

      <div class="form-stack">
        <button
          class="button button--primary button--full"
          type="button"
          :disabled="busy"
          @click="refreshVerification"
        >
          <span v-if="busy" class="spinner" aria-hidden="true"></span>
          I opened the verification link
        </button>
        <button
          class="button button--secondary button--full"
          type="button"
          :disabled="busy"
          @click="resend"
        >
          Send another email
        </button>
        <a
          v-if="localTools"
          class="text-link verification-mailpit"
          href="http://localhost:8025"
          target="_blank"
          rel="noreferrer"
        >
          Open local Mailpit <AppIcon name="arrow" :size="16" />
        </a>
      </div>

      <button class="text-button verification-signout" type="button" @click="signOut">
        Sign out and use another account
      </button>
    </section>

    <aside class="auth-story" aria-label="Why KeepProof verifies email">
      <div class="auth-story__card">
        <span class="feature-icon feature-icon--large"><AppIcon name="check" :size="27" /></span>
        <blockquote>Receipts and reminders stay attached to an address you control.</blockquote>
        <p>Local development email is captured by Mailpit and never leaves your machine.</p>
      </div>
    </aside>
  </main>
</template>

<style scoped>
.verification-mailpit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
}

.verification-signout {
  margin-top: 1.5rem;
}
</style>
