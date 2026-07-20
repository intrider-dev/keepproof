<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import BrandMark from '../components/BrandMark.vue'
import { ApiProblem, apiRequest } from '../lib/api'
import { validateInviteEmail } from '../validation'

const email = ref('')
const fieldError = ref('')
const requestError = ref('')
const sent = ref(false)
const busy = ref(false)

const submit = async (): Promise<void> => {
  if (busy.value) return
  fieldError.value = validateInviteEmail(email.value) ?? ''
  requestError.value = ''
  if (fieldError.value) return

  busy.value = true
  try {
    await apiRequest<{ message: string }>('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email: email.value }),
    })
    sent.value = true
  } catch (error) {
    requestError.value =
      error instanceof ApiProblem
        ? error.problem.detail
        : 'The reset request could not be sent. Please try again.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main id="main-content" class="auth-page" tabindex="-1">
    <section class="auth-panel" aria-labelledby="forgot-password-title">
      <RouterLink class="brand" to="/"><BrandMark /><span>KeepProof</span></RouterLink>
      <div class="auth-panel__heading">
        <p class="eyebrow">Account recovery</p>
        <h1 id="forgot-password-title">Reset your password</h1>
        <p>Enter the email used for your private workspace.</p>
      </div>

      <div v-if="sent" class="auth-success" role="status">
        <span class="feature-icon"><AppIcon name="check" :size="20" /></span>
        <div>
          <strong>Check your inbox</strong>
          <p>
            If an account matches that address, a single-use reset link is on its way. Local mail
            appears in Mailpit.
          </p>
        </div>
      </div>

      <form v-else class="form-stack" novalidate @submit.prevent="submit">
        <div class="field">
          <label for="forgot-email">Email address</label>
          <input
            id="forgot-email"
            v-model="email"
            class="input"
            type="email"
            autocomplete="email"
            :aria-invalid="Boolean(fieldError)"
            :aria-describedby="fieldError ? 'forgot-email-error' : undefined"
          />
          <p v-if="fieldError" id="forgot-email-error" class="field-error">{{ fieldError }}</p>
        </div>
        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>
        <button class="button button--primary button--full" type="submit" :disabled="busy">
          <span v-if="busy" class="spinner" aria-hidden="true"></span>
          {{ busy ? 'Sending reset link…' : 'Send reset link' }}
        </button>
      </form>

      <p class="auth-switch"><RouterLink to="/login">Back to sign in</RouterLink></p>
    </section>
    <aside class="auth-story" aria-label="Password reset security">
      <div class="auth-story__card">
        <span class="feature-icon feature-icon--large"><AppIcon name="shield" :size="27" /></span>
        <h2>Private even during recovery</h2>
        <p>
          The response never reveals whether an email is registered. Reset links expire and can be
          used only once.
        </p>
      </div>
      <div class="auth-story__foot">
        <AppIcon name="check" :size="18" /> Generic responses prevent account discovery.
      </div>
    </aside>
  </main>
</template>
