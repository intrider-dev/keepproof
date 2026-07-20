import { createRouter, createWebHistory } from 'vue-router'
import { appStore } from './stores/app'
import AppShell from './components/AppShell.vue'
import LandingPage from './pages/LandingPage.vue'
import LoginPage from './pages/LoginPage.vue'
import RegisterPage from './pages/RegisterPage.vue'
import ForgotPasswordPage from './pages/ForgotPasswordPage.vue'
import ResetPasswordPage from './pages/ResetPasswordPage.vue'
import EmailVerificationPage from './pages/EmailVerificationPage.vue'
import DemoEntryPage from './pages/DemoEntryPage.vue'
import DashboardPage from './pages/DashboardPage.vue'
import PurchaseListPage from './pages/PurchaseListPage.vue'
import PurchaseDetailPage from './pages/PurchaseDetailPage.vue'
import UploadReceiptPage from './pages/UploadReceiptPage.vue'
import ManualPurchasePage from './pages/ManualPurchasePage.vue'
import ExtractionReviewPage from './pages/ExtractionReviewPage.vue'
import DeadlinesPage from './pages/DeadlinesPage.vue'
import NotificationSettingsPage from './pages/NotificationSettingsPage.vue'
import HouseholdPage from './pages/HouseholdPage.vue'
import PrivacyPage from './pages/PrivacyPage.vue'
import DeleteAccountPage from './pages/DeleteAccountPage.vue'
import NotFoundPage from './pages/NotFoundPage.vue'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/', component: LandingPage, meta: { title: 'Private receipt tracking' } },
    { path: '/login', component: LoginPage, meta: { title: 'Sign in' } },
    { path: '/register', component: RegisterPage, meta: { title: 'Create account' } },
    { path: '/forgot-password', component: ForgotPasswordPage, meta: { title: 'Reset password' } },
    { path: '/reset-password', component: ResetPasswordPage, meta: { title: 'Choose a password' } },
    {
      path: '/verify-email',
      component: EmailVerificationPage,
      meta: { requiresAuth: true, title: 'Verify email' },
    },
    { path: '/demo', component: DemoEntryPage, meta: { title: 'Starting local demo' } },
    {
      path: '/docs/api',
      component: () => import('./pages/ApiDocsPage.vue'),
      meta: { title: 'API documentation' },
    },
    {
      path: '/app',
      component: AppShell,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/app/dashboard' },
        {
          path: 'dashboard',
          component: DashboardPage,
          meta: { requiresAuth: true, title: 'Dashboard' },
        },
        {
          path: 'purchases',
          component: PurchaseListPage,
          meta: { requiresAuth: true, title: 'Purchases' },
        },
        {
          path: 'purchases/new',
          component: UploadReceiptPage,
          meta: { requiresAuth: true, title: 'Add a purchase' },
        },
        {
          path: 'purchases/new/manual',
          component: ManualPurchasePage,
          meta: { requiresAuth: true, title: 'Enter a purchase' },
        },
        {
          path: 'purchases/:id',
          component: PurchaseDetailPage,
          meta: { requiresAuth: true, title: 'Purchase details' },
        },
        {
          path: 'review/:documentId',
          component: ExtractionReviewPage,
          meta: { requiresAuth: true, title: 'Review extraction' },
        },
        {
          path: 'deadlines',
          component: DeadlinesPage,
          meta: { requiresAuth: true, title: 'Deadline timeline' },
        },
        {
          path: 'notifications',
          component: NotificationSettingsPage,
          meta: { requiresAuth: true, title: 'Notification settings' },
        },
        {
          path: 'household',
          component: HouseholdPage,
          meta: { requiresAuth: true, title: 'Household members' },
        },
        {
          path: 'privacy',
          component: PrivacyPage,
          meta: { requiresAuth: true, title: 'Privacy & data' },
        },
        {
          path: 'privacy/delete',
          component: DeleteAccountPage,
          meta: { requiresAuth: true, title: 'Delete account' },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', component: NotFoundPage, meta: { title: 'Page not found' } },
  ],
})

router.beforeEach(async (to) => {
  if (to.meta.requiresAuth && !appStore.state.isAuthenticated) {
    try {
      const restored = await appStore.restoreSession()
      if (!restored) return { path: '/login', query: { redirect: to.fullPath } }
    } catch {
      return { path: '/login', query: { unavailable: '1', redirect: to.fullPath } }
    }
  }
  if (to.meta.requiresAuth && !appStore.state.user.emailVerified && to.path !== '/verify-email') {
    return '/verify-email'
  }
  if (to.path === '/verify-email' && appStore.state.user.emailVerified) return '/app/dashboard'
  return true
})

router.afterEach((to) => {
  const title = typeof to.meta.title === 'string' ? to.meta.title : 'KeepProof'
  document.title = `${title} · KeepProof`
  requestAnimationFrame(() => {
    document.querySelector<HTMLElement>('#main-content')?.focus({ preventScroll: true })
  })
})

export default router
