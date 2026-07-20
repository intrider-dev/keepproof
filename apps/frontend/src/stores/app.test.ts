import { afterEach, describe, expect, it, vi } from 'vitest'
import type { UserResponse } from '../api/generated'
import { appStore } from './app'

const jsonResponse = (body: unknown): Response =>
  new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  })

const verifiedUser: UserResponse = {
  data: {
    id: '01K00000000000000000000001',
    name: 'Alex Morgan',
    email: 'alex@example.test',
    timezone: 'America/New_York',
    locale: 'en',
    email_verified: true,
    households: [
      {
        id: '01K00000000000000000000002',
        name: 'Morgan household',
        is_personal: true,
        role: 'owner',
      },
    ],
  },
}

afterEach(() => {
  vi.unstubAllGlobals()
  appStore.startDemo()
})

describe('connected application store', () => {
  it('hydrates tenant-scoped workspace data from the API contract', async () => {
    const householdId = verifiedUser.data.households[0]!.id
    const purchaseId = '01K00000000000000000000003'
    const documentId = '01K00000000000000000000004'
    const responses: Record<string, unknown> = {
      [`/api/v1/households?per_page=100`]: {
        data: verifiedUser.data.households,
        links: {},
        meta: {},
      },
      [`/api/v1/households/${householdId}/purchases?per_page=100`]: {
        data: [
          {
            id: purchaseId,
            household_id: householdId,
            state: 'needs_review',
            merchant: 'Northstar Home',
            purchase_date: '2026-07-08',
            timezone: 'America/New_York',
            receipt_number: 'NS-1042',
            currency: 'USD',
            total_minor: 13950,
            country_region: 'US',
            notes: null,
            items: [
              {
                id: '01K00000000000000000000005',
                name: 'Linen floor lamp',
                quantity: 1,
                unit_price_minor: 13950,
              },
            ],
            deadlines: [
              {
                id: '01K00000000000000000000006',
                kind: 'return',
                due_at: '2026-08-07T04:00:00Z',
                due_on: '2026-08-07',
                timezone: 'America/New_York',
                source: 'calculated',
                rule_summary: '30 calendar days after purchase',
                trace: [],
                reminder_intervals: [7, 3, 1],
                active: true,
              },
            ],
          },
        ],
        links: {},
        meta: {},
      },
      [`/api/v1/households/${householdId}/dashboard`]: {
        data: {
          needs_review: [
            {
              id: documentId,
              purchase_id: purchaseId,
              household_id: householdId,
              state: 'needs_review',
              filename: 'northstar-receipt.png',
              mime_type: 'image/png',
              size_bytes: 8809,
              processing_attempts: 1,
              retain_original: true,
              fields: [
                {
                  key: 'merchant',
                  value: 'Northstar Home',
                  normalized_value: 'Northstar Home',
                  confidence: '0.9800',
                  source: 'ocr:MERCHANT',
                  validation_errors: [],
                  confirmed_value: null,
                },
                {
                  key: 'total_minor',
                  value: '139.50',
                  normalized_value: '13950',
                  confidence: '0.9500',
                  source: 'ocr:TOTAL',
                  validation_errors: [],
                  confirmed_value: null,
                },
              ],
            },
          ],
          scheduled_reminders: [
            {
              id: '01K00000000000000000000008',
              channel: 'in_app',
              status: 'scheduled',
              interval_days: 3,
              remind_at: '2026-08-04T13:00:00Z',
              deadline: {
                id: '01K00000000000000000000006',
                kind: 'return',
                purchase_id: purchaseId,
                merchant: 'Northstar Home',
              },
            },
          ],
        },
      },
      [`/api/v1/households/${householdId}/members`]: {
        data: [
          {
            id: '01K00000000000000000000007',
            role: 'owner',
            user: {
              id: verifiedUser.data.id,
              name: verifiedUser.data.name,
              email: verifiedUser.data.email,
            },
          },
        ],
      },
      '/api/v1/notification-preferences': {
        data: {
          in_app_enabled: true,
          email_enabled: true,
          return_intervals: [7, 3, 1],
          warranty_intervals: [30, 7],
          quiet_hours_start: '22:00',
          quiet_hours_end: '07:00',
          timezone: 'America/New_York',
        },
      },
      '/api/v1/notifications?per_page=20': {
        data: [
          {
            id: '8dc2072b-0d44-4c42-ac12-ec9ea694ac2e',
            data: {
              purchase_id: purchaseId,
              deadline_id: '01K00000000000000000000006',
              kind: 'return',
              merchant: 'Northstar Home',
              due_on: 'August 7, 2026',
              message: 'Return deadline for Northstar Home is approaching.',
            },
            read_at: null,
            created_at: '2026-07-20T13:00:00Z',
          },
        ],
        links: {},
        meta: {},
      },
    }
    const fetchMock = vi.fn<typeof fetch>(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
      const body = responses[url]
      if (!body) return jsonResponse({ title: 'Not found', status: 404, detail: url })
      return jsonResponse(body)
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(appStore.activateRemoteSession(verifiedUser)).resolves.toBe(true)

    expect(appStore.state.demoMode).toBe(false)
    expect(appStore.state.user.householdId).toBe(householdId)
    expect(appStore.state.purchases[0]).toMatchObject({
      id: purchaseId,
      merchant: 'Northstar Home',
      documentId,
    })
    expect(appStore.state.documents[0]?.fields.find((field) => field.key === 'total')?.value).toBe(
      '139.50',
    )
    expect(appStore.state.members[0]).toMatchObject({ role: 'Owner', email: 'alex@example.test' })
    expect(appStore.unreadNotifications.value).toHaveLength(1)
    expect(appStore.state.scheduledReminders[0]).toMatchObject({
      merchant: 'Northstar Home',
      intervalDays: 3,
    })
    expect(appStore.state.preferences.reminderDays).toEqual([30, 7, 3, 1])
    expect(fetchMock).toHaveBeenCalledTimes(6)
  })

  it('does not load protected household data before email verification', async () => {
    const fetchMock = vi.fn<typeof fetch>()
    vi.stubGlobal('fetch', fetchMock)

    const unverified: UserResponse = {
      data: { ...verifiedUser.data, email_verified: false },
    }

    await expect(appStore.activateRemoteSession(unverified)).resolves.toBe(false)
    expect(appStore.state.isAuthenticated).toBe(true)
    expect(appStore.state.user.emailVerified).toBe(false)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('clears all user and household data from memory after sign out', async () => {
    appStore.startDemo()
    expect(appStore.state.purchases.length).toBeGreaterThan(0)
    expect(appStore.state.user.email).toBe('demo@keepproof.local')

    await appStore.signOut()

    expect(appStore.state.isAuthenticated).toBe(false)
    expect(appStore.state.demoMode).toBe(false)
    expect(appStore.state.user).toMatchObject({
      id: '',
      name: '',
      email: '',
      emailVerified: false,
      householdId: '',
      householdName: '',
      timezone: 'UTC',
    })
    expect(appStore.state.purchases).toEqual([])
    expect(appStore.state.documents).toEqual([])
    expect(appStore.state.members).toEqual([])
    expect(appStore.state.notifications).toEqual([])
    expect(appStore.state.scheduledReminders).toEqual([])
  })
})

describe('demo household membership', () => {
  it('supports fixture removal and ownership transfer without an API request', async () => {
    const fetchMock = vi.fn<typeof fetch>()
    vi.stubGlobal('fetch', fetchMock)
    appStore.startDemo()

    await appStore.transferOwnership('member-sam', '')
    expect(appStore.state.members.find((member) => member.id === 'member-sam')?.role).toBe('Owner')
    expect(appStore.state.members.find((member) => member.id === 'member-alex')?.role).toBe(
      'Member',
    )

    await appStore.removeMember('member-alex')
    expect(appStore.state.members.map((member) => member.id)).toEqual(['member-sam'])
    expect(fetchMock).not.toHaveBeenCalled()
  })
})
