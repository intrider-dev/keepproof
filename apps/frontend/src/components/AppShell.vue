<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AppIcon, { type IconName } from './AppIcon.vue'
import BrandMark from './BrandMark.vue'
import { appStore } from '../stores/app'

const route = useRoute()
const router = useRouter()

const navItems: Array<{ to: string; label: string; icon: IconName }> = [
  { to: '/app/dashboard', label: 'Dashboard', icon: 'home' },
  { to: '/app/purchases', label: 'Purchases', icon: 'receipt' },
  { to: '/app/deadlines', label: 'Deadlines', icon: 'clock' },
  { to: '/app/notifications', label: 'Reminders', icon: 'bell' },
]

const utilityItems: Array<{ to: string; label: string; icon: IconName }> = [
  { to: '/app/household', label: 'Household', icon: 'users' },
  { to: '/app/privacy', label: 'Privacy & data', icon: 'shield' },
]

const pageTitle = computed(() =>
  typeof route.meta.title === 'string' ? route.meta.title : 'KeepProof',
)

const signOut = async (): Promise<void> => {
  await appStore.signOut()
  await router.push('/')
}
</script>

<template>
  <div class="app-layout">
    <aside class="sidebar" aria-label="Primary navigation">
      <RouterLink class="brand brand--app" to="/app/dashboard">
        <BrandMark />
        <span>KeepProof</span>
      </RouterLink>

      <div class="workspace-switcher">
        <span class="avatar avatar--small">MH</span>
        <span>
          <strong>{{ appStore.state.user.householdName }}</strong>
          <small>Private workspace</small>
        </span>
      </div>

      <nav class="side-nav">
        <RouterLink v-for="item in navItems" :key="item.to" :to="item.to">
          <AppIcon :name="item.icon" />
          <span>{{ item.label }}</span>
          <span
            v-if="item.to === '/app/purchases' && appStore.reviewDocuments.value.length"
            class="nav-count"
            :aria-label="`${appStore.reviewDocuments.value.length} purchases need review`"
          >
            {{ appStore.reviewDocuments.value.length }}
          </span>
          <span
            v-if="item.to === '/app/notifications' && appStore.unreadNotifications.value.length"
            class="nav-count"
            :aria-label="`${appStore.unreadNotifications.value.length} unread notifications`"
          >
            {{ appStore.unreadNotifications.value.length }}
          </span>
        </RouterLink>
      </nav>

      <nav class="side-nav side-nav--utility" aria-label="Workspace settings">
        <RouterLink v-for="item in utilityItems" :key="item.to" :to="item.to">
          <AppIcon :name="item.icon" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="sidebar__foot">
        <div class="demo-chip"><span></span> Local demo</div>
        <button class="sidebar-user" type="button" @click="signOut">
          <span class="avatar">{{
            appStore.state.user.name
              .split(' ')
              .map((part) => part[0])
              .join('')
              .slice(0, 2)
          }}</span>
          <span>
            <strong>{{ appStore.state.user.name }}</strong>
            <small>Sign out</small>
          </span>
          <AppIcon name="logout" :size="18" />
        </button>
      </div>
    </aside>

    <div class="app-stage">
      <header class="mobile-appbar">
        <RouterLink class="brand brand--app" to="/app/dashboard">
          <BrandMark />
          <span>KeepProof</span>
        </RouterLink>
        <div class="mobile-appbar__actions">
          <RouterLink class="icon-button" to="/app/purchases/new" aria-label="Add purchase">
            <AppIcon name="plus" :size="19" />
          </RouterLink>
          <RouterLink class="avatar" to="/app/privacy" aria-label="Privacy & data">AM</RouterLink>
        </div>
      </header>
      <header class="app-topbar">
        <p>{{ pageTitle }}</p>
        <RouterLink class="button button--primary button--small" to="/app/purchases/new">
          <AppIcon name="plus" :size="17" />
          Add purchase
        </RouterLink>
      </header>
      <main id="main-content" class="app-main" tabindex="-1">
        <RouterView />
      </main>
    </div>

    <nav class="mobile-nav" aria-label="Primary navigation">
      <RouterLink v-for="item in navItems" :key="item.to" :to="item.to">
        <AppIcon :name="item.icon" />
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>
  </div>
</template>
