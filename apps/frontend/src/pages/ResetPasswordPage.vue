<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import BrandMark from '../components/BrandMark.vue'
import { ApiProblem, apiRequest } from '../lib/api'
import { validatePasswordReset, type ValidationErrors } from '../validation'

const route = useRoute()
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  passwordConfirmation: '',
})
const errors = ref<ValidationErrors>({})
const apiErrors = ref<Record<string, string[]>>({})
const requestError = ref('')
const complete = ref(false)
const busy = ref(false)

const fieldError = (key: string, apiKey = key): string | undefined =>
  errors.value[key] ?? apiErrors.value[apiKey]?.[0]

const submit = async (): Promise<void> => {
  if (busy.value) return
  errors.value = validatePasswordReset(
    form.email,
    form.password,
    form.passwordConfirmation,
    token.value,
  )
  apiErrors.value = {}
  requestError.value = ''
  if (Object.keys(errors.value).length) return

  busy.value = true
  try {
    await apiRequest<void>('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify({
        token: token.value,
        email: form.email,
        password: form.password,
        password_confirmation: form.passwordConfirmation,
      }),
    })
    complete.value = true
  } catch (error) {
    if (error instanceof ApiProblem) {
      apiErrors.value = error.problem.errors ?? {}
      requestError.value = error.problem.detail
    } else {
      requestError.value = 'Your password could not be reset. Please request a new link.'
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main id="main-content" class="auth-page" tabindex="-1">
    <section class="auth-panel" aria-labelledby="reset-password-title">
      <RouterLink class="brand" to="/"><BrandMark /><span>KeepProof</span></RouterLink>
      <div class="auth-panel__heading">
        <p class="eyebrow">Single-use link</p>
        <h1 id="reset-password-title">Choose a new password</h1>
        <p>Use a unique passphrase with at least 12 characters.</p>
      </div>

      <div v-if="complete" class="auth-success" role="status">
        <span class="feature-icon"><AppIcon name="check" :size="20" /></span>
        <div>
          <strong>Password updated</strong>
          <p>Your reset token has been consumed. You can now sign in with the new password.</p>
          <RouterLink class="button button--primary" to="/login">Continue to sign in</RouterLink>
        </div>
      </div>

      <form v-else class="form-stack" novalidate @submit.prevent="submit">
        <p v-if="fieldError('link')" class="form-alert" role="alert">
          {{ fieldError('link') }}
        </p>
        <div class="field">
          <label for="reset-email">Email address</label>
          <input
            id="reset-email"
            v-model="form.email"
            class="input"
            type="email"
            autocomplete="email"
            :aria-invalid="Boolean(fieldError('email'))"
          />
          <p v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</p>
        </div>
        <div class="field">
          <label for="reset-password">New password</label>
          <input
            id="reset-password"
            v-model="form.password"
            class="input"
            type="password"
            autocomplete="new-password"
            :aria-invalid="Boolean(fieldError('password'))"
          />
          <p v-if="fieldError('password')" class="field-error">{{ fieldError('password') }}</p>
        </div>
        <div class="field">
          <label for="reset-password-confirmation">Confirm new password</label>
          <input
            id="reset-password-confirmation"
            v-model="form.passwordConfirmation"
            class="input"
            type="password"
            autocomplete="new-password"
            :aria-invalid="Boolean(fieldError('passwordConfirmation', 'password_confirmation'))"
          />
          <p v-if="fieldError('passwordConfirmation', 'password_confirmation')" class="field-error">
            {{ fieldError('passwordConfirmation', 'password_confirmation') }}
          </p>
        </div>
        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>
        <button class="button button--primary button--full" type="submit" :disabled="busy">
          <span v-if="busy" class="spinner" aria-hidden="true"></span>
          {{ busy ? 'Updating password…' : 'Update password' }}
        </button>
      </form>

      <p class="auth-switch"><RouterLink to="/forgot-password">Request a new link</RouterLink></p>
    </section>
    <aside class="auth-story" aria-label="Password guidance">
      <div class="auth-story__card">
        <span class="feature-icon feature-icon--large"><AppIcon name="shield" :size="27" /></span>
        <h2>One account, private proof</h2>
        <p>
          Resetting your password rotates the remember token. Existing receipt files remain private
          and tenant-scoped.
        </p>
      </div>
    </aside>
  </main>
</template>
