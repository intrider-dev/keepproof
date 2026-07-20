<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { appStore } from '../stores/app'
import { ApiProblem } from '../lib/api'
import { validateInviteEmail } from '../validation'

const email = ref('')
const error = ref('')
const busy = ref(false)
const pendingRemovalId = ref('')
const memberActionError = ref('')
const memberBusyId = ref('')
const transferMemberId = ref('')
const transferPassword = ref('')
const transferError = ref('')
const transferBusy = ref(false)

const currentMembership = computed(() =>
  appStore.state.members.find((member) => member.email === appStore.state.user.email),
)
const canManageMembers = computed(() => currentMembership.value?.role === 'Owner')
const transferableMembers = computed(() =>
  appStore.state.members.filter((member) => member.role === 'Member'),
)
const canTransfer = computed(
  () =>
    Boolean(transferMemberId.value) &&
    (appStore.state.demoMode || transferPassword.value.length > 0),
)

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

const removeMember = async (memberId: string): Promise<void> => {
  if (memberBusyId.value) return
  memberBusyId.value = memberId
  memberActionError.value = ''
  try {
    await appStore.removeMember(memberId)
    if (transferMemberId.value === memberId) transferMemberId.value = ''
    pendingRemovalId.value = ''
  } catch (caught) {
    memberActionError.value =
      caught instanceof ApiProblem ? caught.problem.detail : 'The member could not be removed.'
  } finally {
    memberBusyId.value = ''
  }
}

const transferOwnership = async (): Promise<void> => {
  if (transferBusy.value || !canTransfer.value) return
  transferBusy.value = true
  transferError.value = ''
  try {
    await appStore.transferOwnership(transferMemberId.value, transferPassword.value)
    transferMemberId.value = ''
    transferPassword.value = ''
  } catch (caught) {
    transferError.value =
      caught instanceof ApiProblem ? caught.problem.detail : 'Ownership could not be transferred.'
  } finally {
    transferBusy.value = false
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
            <div v-if="canManageMembers && member.role !== 'Owner'" class="member-actions">
              <template v-if="pendingRemovalId === member.id">
                <button class="button-link" type="button" @click="pendingRemovalId = ''">
                  Cancel
                </button>
                <button
                  class="button-link button-link--danger"
                  type="button"
                  :disabled="memberBusyId === member.id"
                  @click="removeMember(member.id)"
                >
                  {{ memberBusyId === member.id ? 'Removing…' : 'Confirm remove' }}
                </button>
              </template>
              <button
                v-else
                class="button-link button-link--danger"
                type="button"
                @click="pendingRemovalId = member.id"
              >
                Remove
              </button>
            </div>
          </li>
        </ul>
        <p v-if="memberActionError" class="form-alert" role="alert">{{ memberActionError }}</p>
      </section>

      <aside v-if="canManageMembers" class="panel invite-panel">
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
      <aside v-else class="panel invite-panel">
        <span class="feature-icon feature-icon--large"><AppIcon name="shield" :size="27" /></span>
        <p class="eyebrow">Member access</p>
        <h2>Your proof stays shared</h2>
        <p>
          You can view household purchases and documents. The current owner manages invitations and
          membership changes.
        </p>
      </aside>
    </div>

    <section
      v-if="canManageMembers && transferableMembers.length > 0"
      class="panel ownership-panel"
      aria-labelledby="ownership-title"
    >
      <div>
        <p class="eyebrow">Ownership safeguard</p>
        <h2 id="ownership-title">Transfer the household before leaving</h2>
        <p>
          The new owner must already be a member. Purchases and private files stay in this
          household; your account remains a member until you leave or delete it.
        </p>
      </div>
      <form class="ownership-form" @submit.prevent="transferOwnership">
        <label class="field">
          <span>New owner</span>
          <select v-model="transferMemberId" class="input" required>
            <option value="" disabled>Select a member</option>
            <option v-for="member in transferableMembers" :key="member.id" :value="member.id">
              {{ member.name }} · {{ member.email }}
            </option>
          </select>
        </label>
        <label v-if="!appStore.state.demoMode" class="field">
          <span>Confirm your password</span>
          <input
            v-model="transferPassword"
            class="input"
            type="password"
            autocomplete="current-password"
            required
          />
        </label>
        <p v-else class="field-hint">
          Fixture demo: the role change stays in this browser and no password is sent.
        </p>
        <p v-if="transferError" class="form-alert" role="alert">{{ transferError }}</p>
        <button
          class="button button--secondary"
          type="submit"
          :disabled="transferBusy || !canTransfer"
        >
          {{ transferBusy ? 'Transferring…' : 'Transfer ownership' }}
        </button>
      </form>
    </section>

    <div class="legal-note">
      <AppIcon name="shield" />
      <p>
        <strong>Ownership has safeguards.</strong> An owner cannot delete an account while other
        members remain until ownership is transferred.
      </p>
    </div>
  </div>
</template>
