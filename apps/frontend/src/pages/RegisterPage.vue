<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import BrandMark from '../components/BrandMark.vue'
import AppIcon from '../components/AppIcon.vue'
import { ApiProblem, apiRequest } from '../lib/api'
import { demoStore } from '../demo/store'
import { validateRegistration, type ValidationErrors } from '../validation'

const router = useRouter()
const form = reactive({ name: '', email: '', password: '', timezone: 'America/New_York' })
const errors = ref<ValidationErrors>({})
const requestError = ref('')
const busy = ref(false)
const demoEnabled = import.meta.env.VITE_DEMO_MODE !== 'false'

const submit = async (): Promise<void> => {
  if (busy.value) return
  errors.value = validateRegistration(form.name, form.email, form.password)
  requestError.value = ''
  if (Object.keys(errors.value).length) return

  busy.value = true
  try {
    if (!demoEnabled) {
      await apiRequest('/auth/register', {
        method: 'POST',
        body: JSON.stringify(form),
      })
    }
    demoStore.signInDemo(form.name.trim(), form.email.trim())
    demoStore.state.demoMode = demoEnabled
    await router.push('/app/dashboard')
    demoStore.showToast(
      demoEnabled
        ? 'Your private demo household is ready.'
        : 'Account created. Check your email to verify it.',
    )
  } catch (error) {
    requestError.value =
      error instanceof ApiProblem
        ? error.problem.detail
        : 'Account creation failed. Please try again.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main id="main-content" class="auth-page auth-page--register" tabindex="-1">
    <section class="auth-panel" aria-labelledby="register-title">
      <RouterLink class="brand" to="/"><BrandMark /><span>KeepProof</span></RouterLink>
      <div class="auth-panel__heading">
        <p class="eyebrow">Your private workspace</p>
        <h1 id="register-title">Start with one purchase</h1>
        <p>We will create a personal household where your receipts and deadlines stay together.</p>
      </div>
      <form class="form-stack" novalidate @submit.prevent="submit">
        <div class="field">
          <label for="register-name">Your name</label>
          <input
            id="register-name"
            v-model="form.name"
            class="input"
            autocomplete="name"
            :aria-invalid="Boolean(errors.name)"
            :aria-describedby="errors.name ? 'register-name-error' : undefined"
          />
          <p v-if="errors.name" id="register-name-error" class="field-error">{{ errors.name }}</p>
        </div>
        <div class="field">
          <label for="register-email">Email address</label>
          <input
            id="register-email"
            v-model="form.email"
            class="input"
            type="email"
            autocomplete="email"
            :aria-invalid="Boolean(errors.email)"
            :aria-describedby="errors.email ? 'register-email-error' : undefined"
          />
          <p v-if="errors.email" id="register-email-error" class="field-error">
            {{ errors.email }}
          </p>
        </div>
        <div class="field">
          <label for="register-password">Password</label>
          <input
            id="register-password"
            v-model="form.password"
            class="input"
            type="password"
            autocomplete="new-password"
            :aria-invalid="Boolean(errors.password)"
            :aria-describedby="
              errors.password
                ? 'register-password-error register-password-help'
                : 'register-password-help'
            "
          />
          <p id="register-password-help" class="field-hint">Use at least 12 characters.</p>
          <p v-if="errors.password" id="register-password-error" class="field-error">
            {{ errors.password }}
          </p>
        </div>
        <div class="field">
          <label for="register-timezone">Timezone</label>
          <select id="register-timezone" v-model="form.timezone" class="select">
            <option value="America/New_York">Eastern Time (New York)</option>
            <option value="America/Chicago">Central Time (Chicago)</option>
            <option value="America/Denver">Mountain Time (Denver)</option>
            <option value="America/Los_Angeles">Pacific Time (Los Angeles)</option>
            <option value="Europe/London">Europe (London)</option>
            <option value="Asia/Qyzylorda">Asia (Qyzylorda)</option>
          </select>
        </div>
        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>
        <button class="button button--primary button--full" type="submit" :disabled="busy">
          <span v-if="busy" class="spinner" aria-hidden="true"></span>
          {{
            busy
              ? 'Creating your household…'
              : demoEnabled
                ? 'Create demo household'
                : 'Create account'
          }}
        </button>
        <p class="form-fine-print">
          <AppIcon name="shield" :size="16" />
          No advertising trackers. External AI remains off unless you enable it for a document.
        </p>
      </form>
      <p class="auth-switch">
        Already have an account? <RouterLink to="/login">Sign in</RouterLink>
      </p>
    </section>
    <aside class="auth-story auth-story--steps" aria-label="What happens next">
      <p class="eyebrow">What happens next</p>
      <ol>
        <li>
          <span>1</span>
          <p><strong>Verify your email</strong>Your private session is protected.</p>
        </li>
        <li>
          <span>2</span>
          <p><strong>Add a receipt</strong>Use a file or enter details manually.</p>
        </li>
        <li>
          <span>3</span>
          <p><strong>Confirm the dates</strong>Nothing activates before your review.</p>
        </li>
      </ol>
    </aside>
  </main>
</template>
