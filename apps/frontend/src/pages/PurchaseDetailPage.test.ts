import { render, screen } from '@testing-library/vue'
import { afterEach, describe, expect, it } from 'vitest'
import router from '../router'
import { appStore } from '../stores/app'
import PurchaseDetailPage from './PurchaseDetailPage.vue'

afterEach(() => {
  appStore.startDemo()
})

describe('purchase detail output safety', () => {
  it('renders a stored-XSS note as text without creating executable markup', async () => {
    appStore.startDemo()
    const purchase = appStore.state.purchases.find(
      (candidate) => candidate.id === 'purchase-kettle',
    )
    expect(purchase).toBeDefined()
    const payload = '<img src=x onerror="window.__xss=true"><script>alert(1)</script>'
    if (purchase) purchase.notes = payload
    await router.push('/app/purchases/purchase-kettle')
    await router.isReady()

    const { container } = render(PurchaseDetailPage, { global: { plugins: [router] } })

    expect(screen.getByText(payload)).toBeTruthy()
    expect(container.querySelector('script')).toBeNull()
    expect(container.querySelector('[onerror]')).toBeNull()
    expect(container.innerHTML).toContain('&lt;img')
  })
})
