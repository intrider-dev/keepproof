<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import BrandMark from '../components/BrandMark.vue'
import { ApiProblem, apiRequest } from '../lib/api'
import { demoStore } from '../demo/store'
import { validateLogin, type ValidationErrors } from '../validation'

const route = useRoute()
const router = useRouter()
const form = reactive({ email: '', password: '' })
const errors = ref<ValidationErrors>({})
const requestError = ref('')
const busy = ref(false)
const demoEnabled = import.meta.env.VITE_DEMO_MODE !== 'false'

const fillDemo = (): void => {
  form.email = 'demo@keepproof.local'
  form.password = 'KeepProof!2026'
  errors.value = {}
  requestError.value = ''
}

const submit = async (): Promise<void> => {
  if (busy.value) return
  errors.value = validateLogin(form.email, form.password)
  requestError.value = ''
  if (Object.keys(errors.value).length) return

  busy.value = true
  try {
    if (demoEnabled) {
      if (form.email !== 'demo@keepproof.local' || form.password !== 'KeepProof!2026') {
        requestError.value = 'Use the local demo credentials shown below.'
        return
      }
      demoStore.signInDemo()
    } else {
      await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify(form),
      })
      demoStore.signInDemo('Connected user', form.email)
      demoStore.state.demoMode = false
    }
    const redirect =
      typeof route.query.redirect === 'string' ? route.query.redirect : '/app/dashboard'
    await router.push(redirect)
  } catch (error) {
    requestError.value =
      error instanceof ApiProblem ? error.problem.detail : 'Sign in failed. Please try again.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main id="main-content" class="auth-page" tabindex="-1">
    <section class="auth-panel" aria-labelledby="login-title">
      <RouterLink class="brand" to="/"><BrandMark /><span>KeepProof</span></RouterLink>
      <div class="auth-panel__heading">
        <p class="eyebrow">Welcome back</p>
        <h1 id="login-title">Sign in to your proof</h1>
        <p>Return windows, warranties, and receipts—right where you left them.</p>
      </div>

      <div v-if="demoEnabled" class="demo-credentials">
        <div>
          <AppIcon name="spark" :size="18" />
          <span
            ><strong>Local demo</strong><small>demo@keepproof.local · KeepProof!2026</small></span
          >
        </div>
        <button class="text-button" type="button" @click="fillDemo">Fill credentials</button>
      </div>

      <form class="form-stack" novalidate @submit.prevent="submit">
        <div class="field">
          <label for="login-email">Email address</label>
          <input
            id="login-email"
            v-model="form.email"
            class="input"
            type="email"
            autocomplete="email"
            :aria-invalid="Boolean(errors.email)"
            :aria-describedby="errors.email ? 'login-email-error' : undefined"
          />
          <p v-if="errors.email" id="login-email-error" class="field-error">{{ errors.email }}</p>
        </div>
        <div class="field">
          <div class="label-row">
            <label for="login-password">Password</label>
            <span class="muted-label">12+ characters</span>
          </div>
          <input
            id="login-password"
            v-model="form.password"
            class="input"
            type="password"
            autocomplete="current-password"
            :aria-invalid="Boolean(errors.password)"
            :aria-describedby="errors.password ? 'login-password-error' : undefined"
          />
          <p v-if="errors.password" id="login-password-error" class="field-error">
            {{ errors.password }}
          </p>
        </div>
        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>
        <button class="button button--primary button--full" type="submit" :disabled="busy">
          <span v-if="busy" class="spinner" aria-hidden="true"></span>
          {{ busy ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="auth-switch">
        New to KeepProof?
        <RouterLink to="/register">Create a private account</RouterLink>
      </p>
    </section>
    <aside class="auth-story" aria-label="KeepProof privacy summary">
      <div class="auth-story__card">
        <span class="feature-icon feature-icon--large"><AppIcon name="shield" :size="27" /></span>
        <blockquote>“My receipts stay useful without becoming somebody else’s data.”</blockquote>
        <p>A product principle, not a customer testimonial.</p>
      </div>
      <div class="auth-story__foot">
        <AppIcon name="check" :size="18" />
        External AI is off by default.
      </div>
    </aside>
  </main>
</template>
