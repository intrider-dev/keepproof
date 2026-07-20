<script setup lang="ts">
import { ref } from 'vue'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { appStore } from '../stores/app'
import { ApiProblem } from '../lib/api'
import { validateInviteEmail } from '../validation'

const email = ref('')
const error = ref('')
const busy = ref(false)

const invite = async (): Promise<void> => {
  if (busy.value) return
  error.value = validateInviteEmail(email.value) ?? ''
  if (error.value) return
  busy.value = true
  try {
    await appStore.addMember(email.value.trim())
    email.value = ''
  } catch (caught) {
    error.value =
      caught instanceof ApiProblem ? caught.problem.detail : 'The invitation could not be created.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-stack">
    <PageHeader
      :eyebrow="appStore.state.user.householdName"
      title="Proof shared with the right people"
      description="Members can view household purchases and documents. Only the owner can manage membership and household exports."
    />

    <div class="household-summary">
      <div class="household-monogram">MH</div>
      <div>
        <p class="eyebrow">Private workspace</p>
        <h2>{{ appStore.state.user.householdName }}</h2>
        <p>{{ appStore.state.members.length }} members · Private workspace</p>
      </div>
      <StatusPill tone="green"><AppIcon name="shield" :size="14" /> Tenant isolated</StatusPill>
    </div>

    <div class="household-grid">
      <section class="panel">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Access</p>
            <h2>Household members</h2>
          </div>
          <span>{{ appStore.state.members.length }} total</span>
        </div>
        <ul class="member-list">
          <li v-for="member in appStore.state.members" :key="member.id">
            <span class="avatar">{{ member.initials }}</span>
            <div>
              <strong
                >{{ member.name }}
                <span v-if="member.email === appStore.state.user.email">(you)</span></strong
              ><small>{{ member.email }}</small>
            </div>
            <StatusPill :tone="member.role === 'Owner' ? 'green' : 'neutral'">{{
              member.role
            }}</StatusPill>
            <button
              v-if="member.role !== 'Owner'"
              class="icon-button"
              type="button"
              :aria-label="`More options for ${member.name}`"
            >
              •••
            </button>
          </li>
        </ul>
      </section>

      <aside class="panel invite-panel">
        <span class="feature-icon feature-icon--large"><AppIcon name="users" :size="27" /></span>
        <p class="eyebrow">Invite someone</p>
        <h2>Share the household, not the account</h2>
        <p>Each person signs in separately. Their policy checks still apply to every request.</p>
        <form @submit.prevent="invite">
          <label class="field">
            <span>Email address</span>
            <input
              v-model="email"
              class="input"
              type="email"
              placeholder="family@example.com"
              :aria-invalid="Boolean(error)"
              :aria-describedby="error ? 'invite-error' : 'invite-help'"
            />
          </label>
          <p id="invite-help" class="field-hint">
            {{
              appStore.state.demoMode
                ? 'Fixture-demo invitations stay in this browser.'
                : 'The invitee receives a time-limited email invitation.'
            }}
          </p>
          <p v-if="error" id="invite-error" class="field-error">{{ error }}</p>
          <button class="button button--primary button--full" type="submit" :disabled="busy">
            <AppIcon name="plus" :size="17" />
            {{ appStore.state.demoMode ? 'Create demo invitation' : 'Send invitation' }}
          </button>
        </form>
      </aside>
    </div>

    <div class="legal-note">
      <AppIcon name="shield" />
      <p>
        <strong>Ownership has safeguards.</strong> An owner cannot delete an account while other
        members remain until ownership is transferred.
      </p>
    </div>
  </div>
</template>
