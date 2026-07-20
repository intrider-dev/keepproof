import { fireEvent, render, screen } from '@testing-library/vue'
import { afterEach, describe, expect, it } from 'vitest'
import router from '../router'
import { appStore } from '../stores/app'
import NotificationSettingsPage from './NotificationSettingsPage.vue'

afterEach(() => {
  appStore.startDemo()
})

describe('notification settings', () => {
  it('renders from reactive store preferences and edits an independent form snapshot', async () => {
    appStore.startDemo()
    await router.push('/app/notifications')
    await router.isReady()

    render(NotificationSettingsPage, { global: { plugins: [router] } })

    expect(screen.getByRole('heading', { name: 'Your reminders, in one place' })).toBeTruthy()
    const sevenDays = screen.getByRole<HTMLInputElement>('checkbox', {
      name: '7 days before',
    })
    expect(sevenDays.checked).toBe(true)

    await fireEvent.click(sevenDays)

    expect(sevenDays.checked).toBe(false)
    expect(appStore.state.preferences.reminderDays).toContain(7)
  })
})
